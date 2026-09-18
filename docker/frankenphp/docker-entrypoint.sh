#!/bin/sh
set -e

echo "Waiting for database to be ready..."
until pg_isready -h database -p 5432 -U ${POSTGRES_USER:-app} > /dev/null 2>&1; do
    echo "Database is unavailable - sleeping"
    sleep 2
done

echo "Database is ready!"

if [ "${RESET_DATABASE:-false}" = "true" ]; then
    applied=$(php bin/console dbal:run-sql "SELECT COUNT(*) AS applied FROM doctrine_migration_versions" 2>/dev/null | grep -oE '[0-9]+' | head -1)

    if [ -z "${applied}" ] || [ "${applied}" -eq 0 ]; then
        echo "RESET_DATABASE=true - dropping every table before the migrations run"
        php bin/console doctrine:schema:drop --full-database --force --no-interaction
    else
        echo "RESET_DATABASE=true, but ${applied} migrations are already recorded - leaving the data alone"
    fi
fi

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction
    echo "Running database migrations..."
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

    echo "Creating/updating super admin user..."
    php bin/console app:create-super-admin
else
    echo "Skipping migrations - another container owns them"
fi

echo "Starting..."
exec "$@"
