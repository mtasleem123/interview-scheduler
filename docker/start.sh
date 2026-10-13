#!/bin/sh
# Container start-up: fresh SQLite database, demo slots, then serve.
set -e

# A key is needed to encrypt cookies. Use the one from the host if set, otherwise make one.
if [ -z "$APP_KEY" ]; then
    APP_KEY="$(php artisan key:generate --show --no-interaction)"
    export APP_KEY
fi

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
touch "$DB_DATABASE"
php artisan migrate --force --no-interaction
php artisan db:seed --class='Database\Seeders\InterviewSlotSeeder' --force --no-interaction

php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}" --no-reload --no-interaction
