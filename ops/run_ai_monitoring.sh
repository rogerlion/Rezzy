#!/bin/sh
set -eu

ROOT_DIR="/www/wwwroot/geoflow"
MONITOR_DIR="/www/wwwroot/geoflow-monitor"
ENV_FILE="/root/rezzy_geo_monitor.env"
STAMP="$(date +%F-%H%M%S)"
SEARCH_OUT="${MONITOR_DIR}/reports/geo-monitor-${STAMP}.jsonl"
SCORECARD_OUT="${MONITOR_DIR}/reports/ai-scorecard-${STAMP}.jsonl"

mkdir -p "${MONITOR_DIR}/reports"

if [ -f "${ENV_FILE}" ]; then
  set -a
  # shellcheck disable=SC1090
  . "${ENV_FILE}"
  set +a
fi

cd "${MONITOR_DIR}"
python3 tavily_deepseek_monitor.py --prompts prompts.csv --out "${SEARCH_OUT}" --max-results 8 --sleep 1
python3 multi_ai_monitor.py --prompts prompts.csv --out "${SCORECARD_OUT}" --providers deepseek --sleep 1

docker cp "${SEARCH_OUT}" geoflow-app-prod:/tmp/"$(basename "${SEARCH_OUT}")"
docker cp "${SCORECARD_OUT}" geoflow-app-prod:/tmp/"$(basename "${SCORECARD_OUT}")"

SEARCH_BASE="$(basename "${SEARCH_OUT}")"
SCORECARD_BASE="$(basename "${SCORECARD_OUT}")"

cd "${ROOT_DIR}"
docker compose --env-file .env.prod -f docker-compose.prod.yml exec -T app env GEOFLOW_ROOT=/var/www/html \
  php /var/www/html/storage/app/ops/import_ai_monitoring_results.php \
  --search="/tmp/${SEARCH_BASE}" \
  --scorecard="/tmp/${SCORECARD_BASE}"
