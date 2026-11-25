#!/bin/sh

echo "Setting permissions and directories..."
mkdir -p /var/www/html/public/uploads/articles
mkdir -p /var/www/html/storage/app/public/uploads/articles
chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/public/uploads 2>/dev/null || true

# Recreate storage link safely (remove any old stale symlink first)
rm -rf /var/www/html/public/storage 2>/dev/null || true
php artisan storage:link --force || true

echo "Preparing SQLite database always as reliable local backup..."
mkdir -p /var/www/html/database
touch /var/www/html/database/database.sqlite
chmod 777 /var/www/html/database/database.sqlite 2>/dev/null || true
chmod 777 /var/www/html/database 2>/dev/null || true

echo "Running migrations..."
php artisan migrate --force || true
php artisan migrate --force --database=sqlite || true

echo "Migrating existing SQLite data to cloud PostgreSQL if needed..."
php artisan db:migrate-sqlite-to-pgsql || true

echo "Seeding starter articles and admin users if needed..."
php artisan db:seed --class="Database\\Seeders\\DatabaseSeeder" --force || true

chmod -R 777 /var/www/html/database /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

echo "Caching configuration and routes..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "Starting Cricbuzz Live Score Engine on port 6020..."
cd /var/www/html/score-engine && python3 -m uvicorn app:app --host 127.0.0.1 --port 6020 --workers 1 > /var/www/html/storage/logs/score-engine.log 2>&1 &
