#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DB_PATH="$(mktemp /tmp/family-plan-mobile-api.XXXXXX)"
export APP_ENV=test
export APP_DEBUG=0
export REQUIRE_EMAIL_ACTIVATION=false
export MAILER_DSN=null://null
export DATABASE_URL="sqlite:///${DB_PATH}"
export CORS_ALLOWED_ORIGIN="http://localhost:${EXPO_WEB_PORT:-19083}"
API_URL="${MOBILE_API_URL:-http://127.0.0.1:19080}"

cd "$ROOT_DIR"
php bin/console doctrine:schema:create --no-interaction
php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction
exec php -d variables_order=EGPCS -S "${API_URL#http://}" -t public
