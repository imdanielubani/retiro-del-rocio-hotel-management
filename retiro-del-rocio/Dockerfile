# syntax=docker/dockerfile:1
#
# Production image for Retiro Del Rocio (Laravel 13 + Livewire 4 + Tailwind v4).
# Multi-stage: build front-end assets with Node, then serve with a production
# PHP-FPM + Nginx runtime (serversideup/php). Listens on port 8080.

# ---------- Stage 1: build front-end assets (Vite / Tailwind v4) ----------
FROM node:20-alpine AS assets
WORKDIR /app

# Vite inlines every `import.meta.env.VITE_*` reference into the compiled JS
# at BUILD time, not runtime — .env is deliberately excluded from the Docker
# build context (see .dockerignore), so these must arrive as build args here.
# Without them, resources/js/echo.js bakes in a literal `undefined` Reverb
# host, `new Echo(...)` throws synchronously on page load, and — since that
# throw happens inside the first import in app.js — every Alpine component
# registered further down the same file (window.spaReservation,
# gymMembership, restaurantReservation, cinemaBooking, cmsImageUpload) never
# gets defined either, breaking every booking flow on the public site with
# no server-side error to show for it.
#
# Pass these as build args wherever this image is built (Dokploy's build
# args / build-time variables panel, or `docker build --build-arg`) — not as
# ordinary container/runtime env vars, which arrive too late for Vite to see.
ARG VITE_APP_NAME
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT
ARG VITE_REVERB_SCHEME
ENV VITE_APP_NAME=${VITE_APP_NAME} \
    VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY} \
    VITE_REVERB_HOST=${VITE_REVERB_HOST} \
    VITE_REVERB_PORT=${VITE_REVERB_PORT} \
    VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME}

# Install JS deps from the lockfile (cached unless package files change).
COPY package.json package-lock.json ./
RUN npm ci

# Copy the source and build. The full source is copied so Tailwind v4 can scan
# all Blade templates for classes; output goes to public/build.
COPY . .
RUN npm run build


# ---------- Stage 2: PHP runtime (Nginx + PHP-FPM) ----------
# PHP 8.4 — required by the locked deps (Symfony 8.x, spatie/laravel-activitylog 5).
FROM serversideup/php:8.4-fpm-nginx AS app

# The serversideup image serves /var/www/html/public on port 8080 as www-data,
# with Nginx + PHP-FPM managed by s6.

USER root
WORKDIR /var/www/html

# Add PHP extensions:
#  - exif: required by spatie/image + medialibrary
#  - gd:   (re)built WITH libwebp so ImageOptimizer / images:webp can generate
#          .webp on upload. install-php-extensions compiles GD with webp, avif,
#          freetype and jpeg support by default.
RUN install-php-extensions exif gd

# Install PHP dependencies in two steps for better layer caching.
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-autoloader

# Copy the application code.
COPY . .

# Bring in the compiled front-end assets from the Node stage.
COPY --from=assets /app/public/build ./public/build

# Finalise the autoloader and run package discovery.
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && php artisan package:discover --ansi || true

# Create upload dirs + the public/storage symlink at build time (backstop in case
# the runtime entrypoint link ever fails) so uploaded CMS/room/vehicle images serve.
RUN mkdir -p storage/app/public/cms storage/app/public/rooms storage/app/public/vehicles \
        storage/app/public/spa storage/app/public/movies storage/app/public/snacks \
        storage/app/public/restaurant \
    && php artisan storage:link || true

# Boot tasks (storage:link, migrate, config/route/view cache) run on container start.
COPY docker/entrypoint.d/10-laravel.sh /etc/entrypoint.d/10-laravel.sh
RUN chmod +x /etc/entrypoint.d/10-laravel.sh

# The app is copied in as root; hand ownership to the web user so the runtime
# (entrypoint + terminal) can create the storage symlink and write to storage/cache.
RUN chown -R www-data:www-data /var/www/html

# Drop back to the unprivileged runtime user.
USER www-data

EXPOSE 8080 