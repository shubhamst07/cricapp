#!/usr/bin/env bash

echo "Running composer install..."
composer install --no-dev --optimize-autoloader --working-dir=/var/www/html

echo "Setting permissions..."
chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache

echo "Preparing SQLite database if needed..."
if [ "$DB_CONNECTION" = "sqlite" ] || [ -z "$DB_CONNECTION" ]; then
    mkdir -p /var/www/html/database
    touch /var/www/html/database/database.sqlite
    chmod 777 /var/www/html/database/database.sqlite
    chmod 777 /var/www/html/database
fi

echo "Caching configuration and routes..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Running database migrations..."
php artisan migrate --force
