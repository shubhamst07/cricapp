# Stage 1: Build frontend assets
FROM node:20-alpine AS node-builder
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# Stage 2: PHP Application
FROM richarvey/nginx-php-fpm:latest

WORKDIR /var/www/html
COPY . .
COPY --from=node-builder /app/public/build ./public/build

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

EXPOSE 80

CMD ["/start.sh"]
