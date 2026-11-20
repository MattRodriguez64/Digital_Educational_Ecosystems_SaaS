"""Provision project dashboards through the public Kibana 9.5 API.

Run using scripts/setup-kibana-swarm.sh. Existing dashboards are kept unchanged.
No local Python installation, credentials, or generated links file is required.
"""
import json
import os
from pathlib import Path
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parent


def request(base, method, path, payload=None):
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
    data = None if payload is None else json.dumps(payload, ensure_ascii=False).encode('utf-8')
    headers = {'Accept': 'application/json', 'kbn-xsrf': 'campus-setup'}

    if data is not None:
        headers['Content-Type'] = 'application/json'

    api_key = os.environ.get('KIBANA_API_KEY')

    if api_key:
        headers['Authorization'] = 'ApiKey ' + api_key
    req = urllib.request.Request(base + path, data=data, method=method, headers=headers)

    try:
        with opener.open(req, timeout=30) as response:
            return response.status, json.load(response)
    except urllib.error.HTTPError as error:
        # Do not swallow 400/401/403 errors as if an object were missin
        body = error.read().decode('utf-8', errors='replace')

        try:
            result = json.loads(body)
        except ValueError:
            result = {'message': body[:1000]}
        return error.code, result


def ensure_dashboard(base, dashboard_id, payload):
    path = '/api/dashboards/' + urllib.parse.quote(dashboard_id, safe='')
    status, result = request(base, 'GET', path)

    if status == 200:
        existing = result.get('data', {})
        if existing.get('description') != payload['description']:
            raise RuntimeError(f'Identifiant déjà utilisé par un autre tableau de bord: {dashboard_id}')
        print(f"Conservé : {existing.get('title', dashboard_id)}", flush=True)
        return

    if status != 404:
        raise RuntimeError(f'Lecture Kibana HTTP {status}: {result.get("message", "réponse inattendue")}')

    status, result = request(base, 'PUT', path, payload)

    if status not in (200, 201):
        raise RuntimeError(f'Création Kibana HTTP {status}: {result.get("message", "réponse inattendue")}')

    print(f"Créé : {payload['title']} ({dashboard_id})", flush=True)


def main():
    base = os.environ.get('KIBANA_URL', 'http://campus-monitoring_kibana:5601').rstrip('/')
    parsed = urllib.parse.urlparse(base)

    if parsed.scheme not in ('http', 'https') or not parsed.hostname:
        raise RuntimeError('KIBANA_URL doit être une URL HTTP(S)')
        
    for attempt in range(30):
        try:
            status, data = request(base, 'GET', '/api/status')
        except (OSError, urllib.error.URLError):
            status, data = 503, {}
        if status in (401, 403):
            raise RuntimeError('Authentification Kibana requise (KIBANA_API_KEY)')
        if status == 200 and data.get('status', {}).get('overall', {}).get('level') == 'available':
            break
        print('Kibana démarre encore...', flush=True)
        time.sleep(5)
    else:
        raise RuntimeError('Kibana ne devient pas disponible ; consulter ses logs')
    manifest = json.loads((ROOT / 'manifest.json').read_text(encoding='utf-8'))
    for item in manifest.values():
        payload = json.loads((ROOT / item['file']).read_text(encoding='utf-8'))
        ensure_dashboard(base, item['id'], payload)
    print('Tableaux de bord prêts. Les identifiants sont stables après un nouveau clone.', flush=True)


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        print(f'Échec : {error}', file=sys.stderr)
        sys.exit(1)
