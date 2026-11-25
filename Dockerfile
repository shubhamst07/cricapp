# Stage 1: Build frontend assets
FROM node:20-alpine AS node-builder
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# Stage 2: PHP Application + Python Cricbuzz Score Engine
FROM richarvey/nginx-php-fpm:latest

WORKDIR /var/www/html
COPY . .
COPY --from=node-builder /app/public/build ./public/build

# Copy Nginx configuration directly
RUN cp conf/nginx/nginx-site.conf /etc/nginx/sites-available/default.conf && \
    cp conf/nginx/nginx-site.conf /etc/nginx/sites-enabled/default.conf

# Install Python & Cricbuzz Engine dependencies & Postgres drivers
RUN apk add --no-cache bash python3 py3-pip py3-lxml py3-beautifulsoup4 py3-requests py3-pydantic php82-pdo_pgsql php82-pgsql && \
    pip install --no-cache-dir --break-system-packages fastapi uvicorn httpx

# Image configuration variables
ENV SKIP_COMPOSER 1
ENV WEBROOT /var/www/html/public
ENV PHP_ERRORS_STDERR 1
ENV RUN_SCRIPTS 1
ENV REAL_IP_HEADER 1
ENV PHP_CATCHALL true

# Laravel production flags
ENV APP_ENV production
ENV APP_DEBUG false
ENV LOG_CHANNEL stderr
ENV SESSION_DRIVER file
ENV CACHE_STORE file
ENV COMPOSER_ALLOW_SUPERUSER 1
ENV DB_CONNECTION pgsql
ENV DATABASE_URL postgresql://neondb_owner:npg_CkRzSU1qGZ4m@ep-orange-night-bexqhg2o.c-9.us-east-2.aws.neon.tech/neondb?sslmode=require

# Enable tuned PHP OPcache in production (zero per-request disk stat overhead)
RUN for d in /etc/php82/conf.d /etc/php83/conf.d /usr/local/etc/php/conf.d; do \
      if [ -d "$d" ]; then \
        { \
          echo 'opcache.enable=1'; \
          echo 'opcache.memory_consumption=192'; \
          echo 'opcache.interned_strings_buffer=16'; \
          echo 'opcache.max_accelerated_files=20000'; \
          echo 'opcache.validate_timestamps=0'; \
          echo 'opcache.save_comments=1'; \
        } > "$d/99-opcache-prod.ini"; \
      fi; \
    done

# Install composer packages at build time
RUN composer install --no-dev --optimize-autoloader

# Set storage permissions and ensure startup script is executable
RUN chmod -R 777 storage bootstrap/cache && \
    chmod +x scripts/*.sh && \
    mkdir -p /scripts && \
    cp scripts/00-laravel-deploy.sh /scripts/00-laravel-deploy.sh 2>/dev/null || true

EXPOSE 80

CMD ["/start.sh"]
