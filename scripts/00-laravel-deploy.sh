#!/usr/bin/env bash

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

echo "Running migrations..."
php artisan migrate --force

echo "Starting Cricbuzz Live Score Engine on port 6020..."
cd /var/www/html/score-engine && python3 -m uvicorn app:app --host 127.0.0.1 --port 6020 --workers 1 > /var/www/html/storage/logs/score-engine.log 2>&1 &

echo "Starting MediaMTX WebRTC & HLS Live Streaming Server on port 8889/8888..."
if [ ! -f /var/www/html/bin/mediamtx ]; then
    echo "Downloading MediaMTX v1.21.4..."
    mkdir -p /var/www/html/bin
    curl -s -L -A 'Mozilla/5.0' https://github.com/bluenviron/mediamtx/releases/download/v1.21.4/mediamtx_v1.21.4_linux_amd64.tar.gz | tar -xz -C /var/www/html/bin/ mediamtx 2>/dev/null || true
    chmod +x /var/www/html/bin/mediamtx 2>/dev/null || true
fi
if [ -f /var/www/html/bin/mediamtx ]; then
    echo "Launching MediaMTX process..."
    /var/www/html/bin/mediamtx /var/www/html/mediamtx.yml > /var/www/html/storage/logs/mediamtx.log 2>&1 &
fi

