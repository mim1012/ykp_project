# Repository Guidelines

## Project Structure & Module Organization
- `app/` houses Laravel logic (`Http/Controllers` for API endpoints, `Models` for domain entities, `Services` for Supabase integrations).
- `resources/js/` contains the Vite React dashboard (components, pages, hooks) while `resources/views/` provides the Blade entry points.
- `config/` centralizes environment configuration; `database/` tracks migrations and seeders that align with the SQL sync scripts under the repository root.
- `tests/` includes PHPUnit Feature/Unit suites plus `tests/playwright/` for browser coverage; Playwright artifacts land in `playwright-report/`.

## Build, Test & Development Commands
- `npm run dev` starts Vite for hot module reloading; pair with `npm run start:php` or `composer dev` for a full Laravel + queue + Vite stack.
- `npm run build` generates production assets; `npm run build:dashboard` runs the custom `build-dashboard.js` bundler for KPI exports.
- `composer test` clears cached config then runs the Laravel test suite; `npm run test:smoke` triggers the Playwright smoke tag.
- `composer analyse` executes PHPStan at max level, and `composer format` applies Laravel Pint.
- Before shipping, run `npm run predeploy` to compile assets and cache config/routes/views.

## Coding Style & Naming Conventions
- Follow Laravel Pint defaults (PSR-12, 4-space indent) for PHP; favor dependency-injected services over facades in new code.
- Write React components as PascalCase functions in `resources/js/components`; colocate hooks under `hooks/` with camelCase filenames.
- Keep Tailwind utility classes scoped to component-level wrappers and reuse helpers in `resources/js/utils`.

## Testing Guidelines
- Place new PHP specs under `tests/Feature` for HTTP flows or `tests/Unit` for isolated logic; name files after the class under test (e.g., `UserQuotaTest`).
- For Playwright, mirror route names inside `tests/playwright` and tag suites with `@smoke` or `@regression` to aid targeted runs.
- Capture expected metrics or screenshots in `playwright-report/` and attach highlights to PRs when UI changes.

## Commit & Pull Request Guidelines
- Base feature branches on `staging` (see https://github.com/mim1012/ykp_project) and keep them rebased before opening PRs.
- Use Conventional Commit prefixes (`feat:`, `fix:`, `chore:`) to keep the history compatible with the release tooling.
- Write PR descriptions that summarize scope, list test commands executed, and link Jira/GitHub issues; include before/after screenshots for UI work.
- Request review from the relevant domain owners (backend, frontend, data) when touching shared modules, and wait for CI greenlight before merging.
