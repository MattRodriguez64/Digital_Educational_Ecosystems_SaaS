"""One collector per labelled Swarm node; only GET calls to the Docker socket.

Writes usage and hourly billing to Elasticsearch with stable replay IDs.
The SQLite outbox survives collector restarts on the same node.
"""
import datetime as dt
import http.client
import json
import logging
import os
import socket
import sqlite3
import time
import urllib.request
import uuid
from metrics import university, counters, measure, aggregate

ES = os.environ.get('ELASTICSEARCH_URL', 'http://elasticsearch:9200').rstrip('/')
NODE = os.environ['NODE_ID']
OPENER = urllib.request.build_opener(urllib.request.ProxyHandler({}))
logging.basicConfig(level=logging.INFO, format='%(asctime)s %(message)s')


class DockerHTTP(http.client.HTTPConnection):
    def connect(self):
        self.sock = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
        self.sock.settimeout(self.timeout)
        self.sock.connect('/var/run/docker.sock')


def docker(path):
    conn = DockerHTTP('localhost', timeout=8)
    try:
        conn.request('GET', path)
        response = conn.getresponse()
        body = response.read()
        if response.status != 200:
            raise RuntimeError(f'Docker GET {path}: HTTP {response.status}')
        return json.loads(body)
    finally:
        conn.close()


def elastic(method, path, payload):
    request = urllib.request.Request(ES + '/' + path,
              data=json.dumps(payload, allow_nan=False).encode(), method=method,
              headers={'Content-Type': 'application/json'})
    with OPENER.open(request, timeout=15) as response:
        return json.load(response)


def setup():
    fields = {'@timestamp': 'date', 'university': 'keyword', 'node_id': 'keyword',
              'event_id': 'keyword', 'container_ids': 'keyword', 'complete': 'boolean',
              'running': 'integer', 'memory_mib': 'double', 'cpu_pct': 'double',
              'rx_kib_s': 'double', 'tx_kib_s': 'double'}
    elastic('PUT', '_ilm/policy/campus-usage', {
        'policy': {'phases': {'delete': {'min_age': '30d', 'actions': {'delete': {}}}}}})
    elastic('PUT', '_index_template/campus-usage-swarm', {
        'index_patterns': ['campus-usage-swarm-*'], 'priority': 200,
        'template': {'settings': {'number_of_shards': 1, 'number_of_replicas': 0,
                                 'index.lifecycle.name': 'campus-usage'},
                     'mappings': {'properties': {k: {'type': v} for k, v in fields.items()}}}})
    elastic('PUT', '_index_template/campus-billing-hours', {
        'index_patterns': ['campus-billing-hours-*'],
        'template': {'settings': {'number_of_shards': 1, 'number_of_replicas': 0},
                     'mappings': {'dynamic': False, 'properties': {
                         'university': {'type': 'keyword'}, 'period': {'type': 'keyword'},
                         'core_hours': {'type': 'double'}, 'gib_hours': {'type': 'double'},
                         'traffic_gib': {'type': 'double'}}}}})


BILLING_SCRIPT = '''
if (ctx._source.seen == null) {
  ctx._source.seen = [];
  ctx._source.university = params.university;
  ctx._source.period = params.period;
  ctx._source.core_hours = 0.0;
  ctx._source.gib_hours = 0.0;
  ctx._source.traffic_gib = 0.0;
}
if (ctx._source.seen.contains(params.event_id)) { ctx.op = 'none'; }
else {
  ctx._source.seen.add(params.event_id);
  ctx._source.core_hours += params.core_hours;
  ctx._source.gib_hours += params.gib_hours;
  ctx._source.traffic_gib += params.traffic_gib;
}
'''


def deliver(sample):
    stamp = dt.datetime.fromisoformat(sample['@timestamp'].replace('Z', '+00:00'))
    index = 'campus-usage-swarm-' + stamp.strftime('%Y.%m.%d')
    elastic('PUT', index + '/_doc/' + sample['event_id'], sample)
    if sample['complete']:
        # Each node contributes independently. Retrying this event cannot add it twice.
        key = sample['university'] + '-' + sample['node_id'] + '-' + stamp.strftime('%Y%m%d%H')
        params = {k: sample[k] for k in
                  ('university', 'event_id', 'core_hours', 'gib_hours', 'traffic_gib')}
        params['period'] = stamp.strftime('%Y-%m')
        elastic('POST', 'campus-billing-hours-v1/_update/' + key + '?retry_on_conflict=10', {
            'scripted_upsert': True, 'upsert': {},
            'script': {'lang': 'painless', 'source': BILLING_SCRIPT, 'params': params}})


def collect(previous, api):
    groups, next_previous = {}, {}
    for container in docker(api + '/containers/json'):
        name = university(container.get('Labels', {}))
        if not name:
            continue
        group = groups.setdefault(name, {'measures': [], 'ids': [], 'complete': True})
        cid = container['Id']
        group['ids'].append(cid)
        try:
            current = counters(docker(api + '/containers/' + cid + '/stats?stream=false&one-shot=true'),
                               time.monotonic())
            group['measures'].append(measure(current, previous.get(cid)))
            next_previous[cid] = current
        except Exception as error:
            group['complete'] = False
            logging.warning('Statistiques indisponibles %s: %s', name, error)
    stamp = dt.datetime.now(dt.timezone.utc).isoformat(timespec='milliseconds').replace('+00:00', 'Z')
    samples = []
    for name, group in groups.items():
        sample = aggregate(group['measures'])
        if not group['complete']:
            for key in ('memory_mib', 'cpu_pct', 'rx_kib_s', 'tx_kib_s'):
                sample[key] = None
        sample.update(university=name, node_id=NODE, event_id=uuid.uuid4().hex,
                      container_ids=sorted(group['ids']), running=len(group['ids']),
                      complete=group['complete'], **{'@timestamp': stamp})
        samples.append(sample)
    return samples, next_previous


def main():
    os.makedirs('/state', exist_ok=True)
    db = sqlite3.connect('/state/outbox.sqlite')
    db.execute('PRAGMA journal_mode=WAL')
    db.execute('CREATE TABLE IF NOT EXISTS outbox (id INTEGER PRIMARY KEY, payload TEXT NOT NULL)')
    while True:
        try:
            setup()
            api = '/v' + docker('/version')['ApiVersion']
            break
        except Exception as error:
            logging.warning('Initialisation en attente: %s', error)
            time.sleep(10)
    previous = {}
    while True:
        started = time.monotonic()
        try:
            count = db.execute('SELECT count(*) FROM outbox').fetchone()[0]
            if count < 20000:
                samples, next_previous = collect(previous, api)
                with db:
                    db.executemany('INSERT INTO outbox(payload) VALUES (?)',
                                   [(json.dumps(s, allow_nan=False),) for s in samples])
                previous = next_previous
            else:
                previous = {}
                logging.error('File pleine: collecte suspendue, aucun ancien relevé supprimé')
            for row_id, payload in db.execute('SELECT id,payload FROM outbox ORDER BY id LIMIT 100').fetchall():
                deliver(json.loads(payload))
                with db:
                    db.execute('DELETE FROM outbox WHERE id=?', (row_id,))
            logging.info('Collecte %s: %s relevés en attente', NODE,
                         db.execute('SELECT count(*) FROM outbox').fetchone()[0])
        except Exception as error:
            logging.error('Collecte/envoi à réessayer: %s', error)
        time.sleep(max(1, 30 - (time.monotonic() - started)))


if __name__ == '__main__':
    main()
