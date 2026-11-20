#!/bin/sh
set -eu
# Launch from anywhere, files are resolved relative to the repo
PROJECT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
docker run --rm \
  --network campus-backend \
  -e KIBANA_URL="${KIBANA_INTERNAL_URL:-http://campus-monitoring_kibana:5601}" \
  -e KIBANA_API_KEY \
  -v "$PROJECT_DIR/infrastructure/monitoring/kibana:/setup:ro" \
  --entrypoint python \
  localhost:5000/digital-campus-collector:swarm-v1 \
  /setup/setup.py
