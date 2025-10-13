# Repository Guidelines

## Project Structure & Module Organization
- `app/`: Laravel HTTP controllers, models, and services (Supabase under `Services/`).
- `resources/js/`: Vite React dashboard (components, pages, hooks, utils). `resources/views/` hosts Blade entry points.
- `config/`: environment-driven settings. `database/`: migrations/seeders aligned with root SQL sync scripts.
- `tests/`: PHPUnit suites; `tests/playwright/`: browser tests; artifacts in `playwright-report/`.

## Build, Test & Development Commands
- `npm run dev`: Start Vite HMR (front-end only).
- `npm run start:php` or `composer dev`: Run Laravel app + queue (pair with Vite for full stack).
- `npm run build`: Production assets.
- `npm run build:dashboard`: Custom KPI bundler (`build-dashboard.js`).
- `composer test`: Clear caches then run PHP test suite.
- `npm run test:smoke`: Playwright smoke-tagged specs.
- `composer analyse`: PHPStan max-level analysis. `composer format`: Laravel Pint.
- `npm run predeploy`: Compile assets and cache config/routes/views before shipping.

## Coding Style & Naming Conventions
- PHP: PSR-12 via Laravel Pint; 4-space indent; prefer DI services over facades in new code.
- React: components as PascalCase functions in `resources/js/components`; hooks under `hooks/` with camelCase filenames; utilities in `resources/js/utils`.
- CSS: Tailwind utilities scoped to component wrappers; reuse helper classes.

## Testing Guidelines
- PHP: place Feature tests under `tests/Feature` and Unit tests under `tests/Unit` (filename mirrors class, e.g., `UserQuotaTest`). Run with `composer test`.
- Playwright: mirror route names in `tests/playwright`; tag with `@smoke`/`@regression`. Artifacts appear in `playwright-report/`. Run targeted smoke with `npm run test:smoke`.

## Commit & Pull Request Guidelines
- Branch from `staging` and keep rebased. Use Conventional Commits (`feat:`, `fix:`, `chore:` …).
- PRs: summarize scope, list commands run, link Jira/GitHub issues; attach screenshots or metrics (e.g., from `playwright-report/`) for UI changes. Request reviews from relevant domain owners and wait for CI green.

## Security & Configuration Tips
- Store secrets only in `.env`; never commit them. Supabase and external keys are read via `config/`.
- Keep `config/` in sync with environments; after changes, run `npm run predeploy` to refresh caches.
