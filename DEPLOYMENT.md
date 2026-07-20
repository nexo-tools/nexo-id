# Deployment — Nexo ID on Hostinger shared (LiteSpeed)

Nexo ID runs on Laravel 13 + MySQL on Hostinger shared hosting, served from a subdomain via a symlink to `public/`. This is the same playbook the sibling Nexo tools use (`deploy-laravel-hostinger`), plus the OIDC specifics (Passport keys, forced HTTPS). Placeholders: `<host-ssh>` (Hostinger SSH host), `<ssh-user>` (e.g. `u123456`), `<db>`, `<db-user>`, `<db-pass>`.

Assumptions: SSH on port **65002** (hPanel → Advanced → SSH Access — use the exact host from that panel, not the domain's A-record). PHP 8.x + Composer over SSH; **no Node on the server** — assets are built locally/CI and uploaded.

## One-time: hPanel

1. **Subdomain** — create `nexoid.alvarocdev.com` (document root will be replaced by a symlink).
2. **Database** — create a MySQL database + user; note `<db>`, `<db-user>`, `<db-pass>`.
3. **Mailbox** — `nexoid@alvarocdev.com` (SMTP `smtp.hostinger.com:465`).
4. **Repo access** — the repo is private until Phase 3; give the server read access (a read-only deploy key or a PAT) so `git clone`/`pull` works, or upload via `rsync`/`scp`.

## First deploy (over SSH)

```bash
# 1. Code
cd ~/domains/alvarocdev.com
git clone <repo> nexo-id && cd nexo-id
composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# 2. .env (secrets edited by hand)
cp .env.example .env
php artisan key:generate            # BEFORE setting APP_ENV=production
#   Edit .env:
#   APP_ENV=production, APP_DEBUG=false
#   APP_URL=https://nexoid.alvarocdev.com
#   DB_DATABASE=<db>, DB_USERNAME=<db-user>, DB_PASSWORD=<db-pass>
#   SESSION_SECURE_COOKIE=true
#   MAIL_MAILER=smtp, MAIL_SCHEME=smtps, MAIL_HOST=smtp.hostinger.com, MAIL_PORT=465,
#     MAIL_USERNAME=nexoid@alvarocdev.com, MAIL_PASSWORD=..., MAIL_FROM_ADDRESS=nexoid@alvarocdev.com
#   OPENID_FORCE_HTTPS=true
#   NEXO_ATTRIBUTION_URL=https://alvarocdev.com/?utm_source=nexo-id&utm_medium=powered-by

# 3. OAuth signing keys — generated ON THE SERVER, never committed
php artisan passport:keys
chmod 600 storage/oauth-private.key storage/oauth-public.key

# 4. DB + storage
php artisan migrate --force
ln -s "$PWD/storage/app/public" "$PWD/public/storage"   # storage:link fails (exec() disabled)

# 5. Assets — built locally/CI, then uploaded from your machine:
#   npm run build
#   scp -P 65002 -r public/build <ssh-user>@<host-ssh>:~/domains/alvarocdev.com/nexo-id/public/

# 6. Discover packages + production caches
php artisan package:discover
php artisan config:cache && php artisan route:cache && php artisan view:cache

# 7. Point the subdomain at public/ (this turns it on)
cd ~/domains/alvarocdev.com
rm -rf public_html/nexoid            # Hostinger seeds a default.php here
ln -s ~/domains/alvarocdev.com/nexo-id/public public_html/nexoid

# 8. Cron (hPanel → Cron Jobs) — runs the scheduler (nightly passport:purge)
#   * * * * * cd ~/domains/alvarocdev.com/nexo-id && php artisan schedule:run >> /dev/null 2>&1

# 9. Register the first client(s), e.g. Nexo Short:
php artisan nexo:sso-client "Nexo Short" --redirect=https://nxo.li/auth/callback
```

## Post-deploy verification

```bash
curl -sS -o /dev/null -w "%{http_code}\n" https://nexoid.alvarocdev.com                       # 200
curl -sS https://nexoid.alvarocdev.com/.well-known/openid-configuration | grep -o '"issuer":"[^"]*"'  # https, correct host
curl -sS -D - -o /dev/null https://nexoid.alvarocdev.com | grep -i content-security-policy      # strict policy, not upgrade-insecure-requests
```

Then in a browser: register → verify email (real SMTP) → sign in; and drive one full OAuth flow from a client. Check the console for CSP violations.

## Gotchas (LiteSpeed / Hostinger)

- **SSH "connection reset"** → you're on the domain's A-record IP, not the hosting server. Use the panel's SSH host.
- **`storage:link` fails** (`exec()` disabled) → the manual `ln -s` in step 4.
- **CSP overwritten** by LiteSpeed "Force HTTPS" → re-asserted in `public/.htaccess` (kept in sync with the middleware by `SecurityHeadersHtaccessSyncTest`).
- **`public_html/nexoid`** ships a `default.php` → `rm -rf` before the symlink (step 7).
- **Passport keys** must exist on the server and stay out of git; regenerate only with care (invalidates issued tokens).

## Updates

```bash
cd ~/domains/alvarocdev.com/nexo-id
php artisan down && git pull
composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
php artisan package:discover
# upload fresh public/build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

## Production baseline (per standards)

- **Backups**: verified — take a DB dump, restore it once to a scratch DB to prove it works.
- **Uptime monitoring**: a basic check on `https://nexoid.alvarocdev.com/up` (Laravel health route).
