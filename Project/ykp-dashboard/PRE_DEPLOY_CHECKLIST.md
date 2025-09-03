Pre‑Deploy Verification (Staging → Production)

Recommended order to validate RBAC, Sales input, and Stats before deploy.

1) Environment Prep
- APP_ENV: use staging for verification (production blocks dev/test routes).
- DB connection: verify (`DB_CONNECTION`, `DB_HOST`, etc.). For Supabase use pgsql.
- Sessions: if `SESSION_DRIVER=database`, run `php artisan session:table && php artisan migrate` once.

2) Install Dependencies
- PHP deps: `composer install --no-dev -o` (or with dev on staging).
- Node deps: `npm ci`.
- Playwright runtime: `npx playwright install`.

3) Database Migration
- Staging: `php artisan migrate`.
- Production: `php artisan migrate --force` (ensure backup & window).

4) Start App (if not using a web server)
- `php artisan serve` (Playwright config will auto‑start if not running).

5) Smoke Test (RBAC + Sales + Stats)
- Staging (non‑production only): `npm run test:smoke`.
  - Uses quick-login to create a session and hits protected endpoints.

6) Frontend Build
- `npm run build` (or `npm run analyze` if needed to review chunks).

7) Optimize Caches
- `php artisan config:cache && php artisan route:cache && php artisan view:cache`.

8) Production Deploy Notes
- Ensure `APP_DEBUG=false`.
- Disable dev/test routes automatically via APP_ENV=production.
- Confirm 200/302/403 flows: unauthenticated requests must be redirected/denied, role‑based data filtered.

