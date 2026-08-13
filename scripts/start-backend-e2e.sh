#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
API_BASE_URL="${API_BASE_URL:-http://localhost:8080}"
DB_PATH="${ROOT_DIR}/var/test.db"

PORT="$(printf '%s' "$API_BASE_URL" | awk -F: '{print $NF}' | sed 's#/.*##')"
PORT="${PORT:-8080}"

export APP_ENV=test
export APP_DEBUG=0
export DATABASE_URL="sqlite:///${DB_PATH}"
export SUPER_ADMIN_EMAIL="${SUPER_ADMIN_EMAIL:-admin@familyplan.local}"
export SUPER_ADMIN_NAME="${SUPER_ADMIN_NAME:-Super Admin}"
export SUPER_ADMIN_PASSWORD="${SUPER_ADMIN_PASSWORD:-admin123}"

rm -f "$DB_PATH"
rm -rf "${ROOT_DIR}/var/cache/test"

php "${ROOT_DIR}/bin/console" doctrine:schema:create --no-interaction
php "${ROOT_DIR}/bin/console" app:create-super-admin --no-interaction

exec php -d variables_order=EGPCS -S 0.0.0.0:"${PORT}" -t "${ROOT_DIR}/public"
