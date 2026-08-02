# Deployment — Nexo ID on shared hosting (LiteSpeed)

Nexo ID runs on Laravel 13 + MySQL on shared hosting (tested on Hostinger + LiteSpeed), served from a subdomain via a symlink to `public/`. This is the same playbook the sibling Nexo tools use, plus the OIDC specifics (Passport keys, forced HTTPS). Placeholders: `<nexoid-host>` (e.g. `nexoid.example.com`), `<domain>` (the hosting account's domain folder), `<host-ssh>` (panel SSH host), `<ssh-user>` (e.g. `u123456`), `<db>`, `<db-user>`, `<db-pass>`, `<mail-user>` (e.g. `nexoid@example.com`).

Assumptions: SSH on the host and port your panel shows (on Hostinger both live under hPanel → Advanced → SSH Access — a non-default port, and the exact host from that panel, not the domain's A-record). PHP 8.x + Composer over SSH; **no Node on the server** — assets are built locally/CI and uploaded.

## Running it locally

Before deploying anywhere, this is how to get Nexo ID up on your own machine. The README
points here on purpose: keeping the steps in one place is why they stopped drifting.

### Option A — everything in Docker

`compose.yaml` in this repo runs the **app only**: the author's machine keeps a single
MySQL/Mailpit shared by every Nexo tool. This tool's `.env.example` defaults to **SQLite**,
so nothing else is needed to boot it.

```sh
cp .env.example .env
docker compose up -d
docker compose exec laravel.test composer install
docker compose exec laravel.test php artisan key:generate
docker compose exec laravel.test php artisan migrate
npm install && npm run build
```

The app answers on **http://localhost:8100**.

### Option B — MySQL

Production runs on MySQL (see below). To mirror it locally, set `DB_CONNECTION=mysql` plus
`DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` in `.env` and point them
at your own server.

Run the suite with `vendor/bin/pest` (SQLite in memory — it never touches your database).

---

## One-time: hosting panel

1. **Subdomain** — create `<nexoid-host>` (document root will be replaced by a symlink).
2. **Database** — create a MySQL database + user; note `<db>`, `<db-user>`, `<db-pass>`.
3. **Mailbox** — `<mail-user>` for transactional mail (Hostinger: SMTP `smtp.hostinger.com:465`).
4. **Repo access** — give the server read access (a read-only deploy key or a PAT) so `git clone`/`pull` works, or upload via `rsync`/`scp`.

## First deploy (over SSH)

```bash
# 1. Code
cd ~/domains/<domain>
git clone <repo> nexo-id && cd nexo-id
composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# 2. .env (secrets edited by hand)
cp .env.example .env
php artisan key:generate            # BEFORE setting APP_ENV=production
#   Edit .env:
#   APP_ENV=production, APP_DEBUG=false
#   APP_URL=https://<nexoid-host>
#   DB_DATABASE=<db>, DB_USERNAME=<db-user>, DB_PASSWORD=<db-pass>
#   SESSION_SECURE_COOKIE=true
#   MAIL_MAILER=smtp, MAIL_SCHEME=smtps, MAIL_HOST=<smtp-host>, MAIL_PORT=465,
#     MAIL_USERNAME=<mail-user>, MAIL_PASSWORD=..., MAIL_FROM_ADDRESS=<mail-user>
#   OPENID_FORCE_HTTPS=true
#   NEXO_ATTRIBUTION_LABEL / NEXO_ATTRIBUTION_URL per your instance

# 3. OAuth signing keys — generated ON THE SERVER, never committed
php artisan passport:keys
chmod 600 storage/oauth-private.key storage/oauth-public.key

# 4. DB + storage
php artisan migrate --force
ln -s "$PWD/storage/app/public" "$PWD/public/storage"   # storage:link fails (exec() disabled)

# 5. Assets — built locally/CI, then uploaded from your machine:
#   npm run build
#   scp -P <ssh-port> -r public/build <ssh-user>@<host-ssh>:~/domains/<domain>/nexo-id/public/

# 6. Discover packages + production caches
php artisan package:discover
php artisan config:cache && php artisan route:cache && php artisan view:cache

# 7. Point the subdomain at public/ (this turns it on)
cd ~/domains/<domain>
rm -rf public_html/<subdomain-folder>    # the panel seeds a default.php here
ln -s ~/domains/<domain>/nexo-id/public public_html/<subdomain-folder>

# 8. Cron (panel → Cron Jobs) — runs the scheduler: nightly passport:purge AND
#    the per-minute queue drain that mail depends on (routes/console.php).
#    Without this entry no notification ever leaves the queue.
#   * * * * * cd ~/domains/<domain>/nexo-id && php artisan schedule:run >> /dev/null 2>&1

# 9. Register the first client(s) — the redirect must EXACTLY match the tool's
#    callback route (the nexo-sso-client template registers /auth/nexo/callback):
php artisan nexo:sso-client "My Tool" --redirect=https://<tool-host>/auth/nexo/callback
```

## Post-deploy verification

```bash
curl -sS -o /dev/null -w "%{http_code}\n" https://<nexoid-host>                       # 200
curl -sS https://<nexoid-host>/.well-known/openid-configuration | grep -o '"issuer":"[^"]*"'  # https, correct host
curl -sS -D - -o /dev/null https://<nexoid-host> | grep -i content-security-policy      # strict policy, not upgrade-insecure-requests
```

Then in a browser: register → verify email (real SMTP) → sign in; and drive one full OAuth flow from a client. Check the console for CSP violations.

## Gotchas (LiteSpeed / shared hosting)

- **SSH "connection reset"** → you're on the domain's A-record IP, not the hosting server. Use the panel's SSH host.
- **`storage:link` fails** (`exec()` disabled) → the manual `ln -s` in step 4.
- **CSP overwritten** by LiteSpeed "Force HTTPS" → re-asserted in `public/.htaccess` (kept in sync with the middleware by `SecurityHeadersHtaccessSyncTest`).
- **`public_html/<subdomain-folder>`** ships a `default.php` → `rm -rf` before the symlink (step 7).
- **Passport keys** must exist on the server and stay out of git; regenerate only with care (invalidates issued tokens).
- **uuid user columns**: migrations include `fix_oauth_user_id_columns_for_uuid_users` — never skip migrations; non-strict MySQL/MariaDB would otherwise truncate uuids silently.

## Updates

```bash
cd ~/domains/<domain>/nexo-id
php artisan down && git pull
composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
php artisan package:discover
# upload fresh public/build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

## Production baseline (per standards)

- **Backups**: verified — automated DB dumps with at least one restore tested against a scratch DB.
- **Uptime monitoring**: an external check on `https://<nexoid-host>/up` (Laravel health route).
