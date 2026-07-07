# Retiro Del Rocio

Luxury hotel & retreat platform for Retiro Del Rocio (Jos, Plateau State) — public website
(rooms & apartments, airport pick-up, contact) plus a full admin dashboard (bookings, rooms &
room numbers, vehicles, payments, website CMS, notifications).

**Stack**

| Layer | Tech |
|------|------|
| Backend | PHP **8.3+**, Laravel **13** |
| Frontend | Livewire **4**, Alpine.js, Tailwind CSS **v4** (Vite build) |
| Packages | spatie/laravel-permission, laravel-medialibrary, laravel-activitylog |
| Database | **MySQL** (sessions, cache & queue use the `database` driver) |
| Payments | Paystack |
| Mail | SMTP (qServer — `no-reply@retirodelrocio.ng`) |

---

## Deploying to Hostinger VPS with Dokploy

> This app is deployed as a **Dokploy Application** built from the GitHub repo
> `imdanielubani/retiro-del-rocio`. Pushing to the deploy branch triggers a rebuild.

### 0. Prerequisites
- A Hostinger VPS with **Dokploy** installed (dashboard at `http://<vps-ip>:3000`).
- Your website domain **`retirodelrocio.com`** DNS **A record** pointing to the VPS IP (so Dokploy can issue HTTPS). *(Mail is sent from the separate `retirodelrocio.ng` domain — see Mail below.)*
- The GitHub repo connected to Dokploy (GitHub provider or a deploy key).

### 1. Create the database (Dokploy → Databases)
1. **Create Database → MySQL** (e.g. name `retiro-db`, database `retiro`, a strong password).
2. Note the **internal host name** Dokploy assigns — used as `DB_HOST` (it's the service name, **not** `localhost`).

### 2. Create the application (Dokploy → Project → Create Service → Application)
1. **Source:** GitHub → select `imdanielubani/retiro-del-rocio`, branch `main`.
2. **Build Type:** **Dockerfile** (the repo ships a production `Dockerfile`). Dockerfile path: `Dockerfile`.
3. Set the environment variables (next section).
4. **Deploy.**

> The image is multi-stage: it builds the Vite/Tailwind assets with Node, then serves the app with
> **Nginx + PHP-FPM** (serversideup/php) on **port 8080**. On every boot, the bundled entrypoint
> (`docker/entrypoint.d/10-laravel.sh`) waits for the DB, runs `storage:link`, `migrate --force`, and
> caches config/routes/views — so you don't run those by hand.

### 3. Environment variables (Dokploy → Application → Environment)
Paste these and fill in the blanks. Generate `APP_KEY` once locally with `php artisan key:generate --show`.

```env
APP_NAME="Retiro Del Rocio"
APP_ENV=production
APP_KEY=                      # base64:... (php artisan key:generate --show)
APP_DEBUG=false
APP_URL=https://retirodelrocio.com

LOG_CHANNEL=stack
LOG_LEVEL=error

# --- Database (use the Dokploy MySQL service host, NOT 127.0.0.1) ---
DB_CONNECTION=mysql
DB_HOST=retiro-db             # the Dokploy MySQL service/host name
DB_PORT=3306
DB_DATABASE=retiro
DB_USERNAME=retiro
DB_PASSWORD=                  # the password you set in step 1

# database-backed drivers (no Redis needed)
SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

# --- Mail (qServer SMTP) ---
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=mail.retirodelrocio.ng
MAIL_PORT=465
MAIL_USERNAME=no-reply@retirodelrocio.ng
MAIL_PASSWORD=                # mailbox password
MAIL_FROM_ADDRESS="no-reply@retirodelrocio.ng"
MAIL_FROM_NAME="${APP_NAME}"
MAIL_CONTACT_TO="support@retirodelrocio.com"

# --- Paystack (use LIVE keys in production) ---
PAYSTACK_PUBLIC_KEY=
PAYSTACK_SECRET_KEY=
PAYSTACK_PAYMENT_URL=https://api.paystack.co
```

### 4. Persistent storage (Dokploy → Application → Advanced → Volumes)
Uploaded images (room/vehicle photos) live in `storage/app/public`. Without a volume they are
**wiped on every redeploy**. Add a mount:

- **Type:** Volume (named — not a bind mount; a fresh named volume inherits the image's `www-data`
  ownership, so uploads stay writable)
- **Mount Path:** `/var/www/html/storage/app/public`

(The app lives at `/var/www/html`.) Logs in `/var/www/html/storage/logs` are ephemeral, which is fine.

### 5. Domain + HTTPS (Dokploy → Application → Domains)
1. **Add Domain:** `retirodelrocio.com` (add `www.retirodelrocio.com` too if you want it).
2. **HTTPS:** On — Dokploy issues a Let's Encrypt certificate via Traefik automatically.
3. Container **port:** `8080`.

### 6. First-deploy seeding (Dokploy → Application → Terminal)
Migrations, `storage:link`, and caching run **automatically** on every boot (via the entrypoint).
The only manual step is seeding the roles + super admin **once**, on the first deploy:

```bash
php artisan db:seed --force        # FIRST deploy only — roles + super admin + seed data
```

**Default super admin** (from the seeder — change the password after first login):
`admin@retirodelrocio.ng` / `Admin12345`

---

## Updating / redeploying (the "push" workflow)
```bash
git add -A
git commit -m "your change"
git push origin main
```
Dokploy auto-rebuilds on push (enable the GitHub webhook in the app settings, or click **Deploy**).

**No manual post-deploy commands are needed** — the entrypoint re-runs `migrate --force`,
`storage:link`, and config/route/view caching on every boot. (Re-run `php artisan db:seed --force`
manually only if you add new seed data you want applied.)

---

## Important notes

- **MySQL, not SQLite.** `.env.example` ships with SQLite for convenience; production must use the
  MySQL values above.
- **Uploads persistence:** keep the `/var/www/html/storage/app/public` named volume mounted so room/
  vehicle images survive redeploys. The entrypoint re-creates the `public/storage` symlink each boot.
- **Queues:** the app uses `QUEUE_CONNECTION=database`, but mail/notifications send synchronously, so
  **no separate queue worker is required** today. If you later dispatch queued jobs, add a Dokploy
  worker running `php artisan queue:work --tries=3`.
- **Paystack:** set the **callback URL** in your Paystack dashboard to
  `https://retirodelrocio.com/checkout/callback`, and use **live** keys in production.
- **Email deliverability:** `retirodelrocio.ng` has SPF, DKIM, DMARC and PTR configured. Mail is sent
  from `Retiro Del Rocio <no-reply@retirodelrocio.ng>`; guest enquiries and booking alerts go to
  `support@retirodelrocio.com`.
- **`APP_DEBUG=false`** in production, always.

---

## Local development (XAMPP / Windows)
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
# set DB_* to your local MySQL, then:
php artisan migrate --seed
php artisan storage:link
npm run build          # or: npm run dev  (Vite hot reload)
php artisan serve      # http://127.0.0.1:8000
```

> Assets are served from the built manifest, so after changing Blade markup / Tailwind classes run
> `npm run build` (this project runs via `php artisan serve`, not `npm run dev`, in normal workflow).
