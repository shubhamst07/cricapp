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

# Install Python & Cricbuzz Engine dependencies
RUN apk add --no-cache python3 py3-pip py3-lxml py3-beautifulsoup4 py3-requests py3-pydantic && \
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
ENV COMPOSER_ALLOW_SUPERUSER 1

# Install composer packages at build time
RUN composer install --no-dev --optimize-autoloader

# Set storage permissions and ensure startup script is executable
RUN chmod -R 777 storage bootstrap/cache && \
    chmod +x scripts/*.sh && \
    mkdir -p /scripts && \
    cp scripts/00-laravel-deploy.sh /scripts/00-laravel-deploy.sh 2>/dev/null || true

EXPOSE 80

CMD ["/start.sh"]
