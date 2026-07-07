#!/bin/sh
#
# Laravel boot tasks — runs on every container start (serversideup runs every
# executable in /etc/entrypoint.d/ before launching Nginx + PHP-FPM).
#
# Behaviour:
#   - storage:link + caches never abort boot (idempotent, safe to re-run).
#   - migrations run with --force; if the DB is reachable but a migration fails,
#     boot aborts (non-zero exit) so the bad deploy is caught instead of serving
#     a broken app.
set -e

cd /var/www/html

echo "[entrypoint] Retiro Del Rocio — running Laravel boot tasks…"

# --- Wait for the database to accept connections (max ~30s) ---
if [ -n "$DB_HOST" ]; then
    echo "[entrypoint] Waiting for database at ${DB_HOST}:${DB_PORT:-3306}…"
    i=0
    until php -r "exit(@fsockopen(getenv('DB_HOST'), (int)(getenv('DB_PORT') ?: 3306)) ? 0 : 1);" 2>/dev/null; do
        i=$((i + 1))
        if [ "$i" -ge 30 ]; then
            echo "[entrypoint] WARNING: database not reachable after 30s — continuing anyway."
            break
        fi
        sleep 1
    done
fi

# --- Storage symlink (public/storage -> storage/app/public) ---
# Ensure the upload dirs exist (the volume mounts at storage/app/public) and the
# public/storage symlink is (re)created so uploaded CMS/room/vehicle images serve.
mkdir -p storage/app/public/cms storage/app/public/rooms storage/app/public/vehicles
[ -L public/storage ] || rm -rf public/storage
php artisan storage:link --force
echo "[entrypoint] storage symlink: $(readlink -f public/storage 2>/dev/null || echo 'MISSING')"

# --- Database migrations (abort boot on real failure) ---
php artisan migrate --force

# --- Framework caches (config/route/view/event) for production performance ---
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache || true

echo "[entrypoint] Boot tasks complete."
