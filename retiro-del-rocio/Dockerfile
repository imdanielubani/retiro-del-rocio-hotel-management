FROM serversideup/php:8.4-fpm-nginx AS app

USER root
WORKDIR /var/www/html

# Install PHP extensions
RUN install-php-extensions exif gd

# Copy only composer files first
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-autoloader

# Now copy the rest of the application
COPY . .

# Copy built frontend assets
COPY --from=assets /app/public/build ./public/build

# Optimize Laravel
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && php artisan package:discover --ansi || true

RUN mkdir -p storage/app/public/cms storage/app/public/rooms storage/app/public/vehicles \
    && php artisan storage:link || true

COPY docker/entrypoint.d/10-laravel.sh /etc/entrypoint.d/10-laravel.sh
RUN chmod +x /etc/entrypoint.d/10-laravel.sh

RUN chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 8080