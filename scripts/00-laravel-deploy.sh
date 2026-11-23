#!/bin/sh

echo "Setting permissions..."
chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

echo "Preparing SQLite database if needed..."
if [ "$DB_CONNECTION" = "sqlite" ] || [ -z "$DB_CONNECTION" ]; then
    mkdir -p /var/www/html/database
    touch /var/www/html/database/database.sqlite
    chmod 777 /var/www/html/database/database.sqlite 2>/dev/null || true
    chmod 777 /var/www/html/database 2>/dev/null || true
fi

echo "Running migrations..."
php artisan migrate --force || true
chmod -R 777 /var/www/html/database /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

echo "Caching configuration and routes..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "Starting Cricbuzz Live Score Engine on port 6020..."
cd /var/www/html/score-engine && python3 -m uvicorn app:app --host 127.0.0.1 --port 6020 --workers 1 > /var/www/html/storage/logs/score-engine.log 2>&1 &
