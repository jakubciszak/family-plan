#!/bin/sh
set -e

# Wait for database to be ready
echo "Waiting for database to be ready..."
until pg_isready -h database -p 5432 -U ${POSTGRES_USER:-app} > /dev/null 2>&1; do
    echo "Database is unavailable - sleeping"
    sleep 2
done

echo "Database is ready!"

# Run migrations
echo "Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# Create super admin user
echo "Creating/updating super admin user..."
php bin/console app:create-super-admin

echo "Starting PHP-FPM..."
exec php-fpm
