# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

PuchoNow (puchonow.in) — a Q&A community platform in plain PHP 8.3 (no framework), MySQL, and Tailwind CSS, built to run on Hostinger shared hosting. No build step exists on the server: compiled assets are committed and deployed as-is.

## Commands

```bash
npm run build        # build:css (Tailwind → assets/css/tailwind.min.css) + build:js (esbuild assets/js/* → assets/dist/js/*)
npm run watch:css    # Tailwind watcher during development
php -S localhost:8000 router.php   # local dev server (router.php mirrors .htaccess rewrites; php -S ignores .htaccess)
php -l <file>        # lint — syntax-lint every touched file
php tools/smoke_test.php [base-url]   # ~51 route checks; exit 0 = pass. Run before/after every deploy
```

`tools/smoke_test.php` is the closest thing to a test suite: it asserts public pages return 200, auth-gated pages 302 (a 200 there = auth regression), unknown routes 404, and that secret paths (`.env`, `config.php`, `includes/*`, migrations) never leak config markers in their body. Requests are sequential on purpose — parallel bursts trip shared-hosting concurrency limits and produce false 500s. Add a route here whenever you add a page.

Local setup: copy `config.sample.php` to `config.php`, create a MySQL DB, apply `schema.sql` then every file in `migrations/` in numeric order. Configuration comes from `.env` (loaded by `includes/env.php`); `config.php` just maps env vars to constants.

**Run `npm run build` before every deploy** — the server has no npm. Deployment is FTP upload of changed files (no CI). After editing anything in `assets/js/`, the minified copy in `assets/dist/js/` is what pages actually load.

## Architecture

- **Page = file.** Every public page is a root-level PHP file; `/login` resolves to `login.php` via a generic extensionless rewrite in `.htaccess` (mirrored by `router.php` for `php -S`). Slugged routes: `/q/<slug>` → `question.php`, `/c/` categories, `/tag/`, `/u/` profiles, `/g/` groups, `/blog/`, `/page/` CMS pages.
- **Every page starts with `require includes/auth.php`.** That include boots everything: session, remember-me auto-login, IP-block gate, CSRF helpers (`csrf_field()`/`verify_csrf()`), rate limiters, honeypot helpers, and starts the HTML-minifying output buffer (`includes/minify.php`; define `SKIP_HTML_MINIFY` before the require to opt out — non-HTML output is auto-skipped).
- **Page skeleton:** POST handling at the top (always `verify_csrf()` first, then `redirect()` on success — PRG pattern), then data queries, then `$pageTitle`/`$pageDescription`/optional `$ogImage`, then `require includes/header.php`, HTML, `require includes/footer.php`.
- **DB access** is the `db()` singleton (PDO, exceptions, real prepares) from `includes/db.php`. All queries use prepared statements; the only interpolation allowed is whitelisted column/order fragments.
- **Output escaping:** `e()` from `includes/functions.php` on every echo of dynamic data, no exceptions.
- **Settings** live in the `site_settings` table via `setting(key, default)` (per-request cached). Site name/branding, captcha keys, Telegram, payments mode etc. are all settings, not constants — `SITE_NAME` from `.env` is the fallback.
- **Admin** pages live in `admin/`, start with `require admin/includes/admin_header.php` (which enforces `require_role('admin','moderator')`), and use `require_permission()` from `includes/permissions.php` for granular moderator rights. Mutations call `audit_log()`.
- **API v1** (`api/v1/`) authenticates via `api_authenticate()` in `includes/api_auth.php` (hashed bearer keys, scopes, per-key file-bucket rate limits). Internal AJAX endpoints (`api/*.php`) instead use the session + CSRF token (`window.CSRF_TOKEN`).
- **Migrations** are plain SQL files in `migrations/`, applied manually via phpMyAdmin in production. Never edit an applied migration; add a new numbered file. Seeds use `INSERT IGNORE` so re-running is safe. Code must tolerate a missing table mid-migration (wrap in try/catch, fail open) — see `dash_count()` in `admin/dashboard.php` for the pattern.
- **Notifications** are rows in `notifications` (`user_id`, `type`, JSON `data`); rendering is a type switch in `inbox.php` — adding a type means inserting rows *and* adding a render branch there.
- **Payments** are gateway classes in `includes/payments/` behind a common interface; webhooks (`api/webhook_*.php`) verify signatures before processing.
- **Crons** in `cron/` are CLI-only (`php_sapi_name() !== 'cli'` guard) and scheduled in hPanel, not in code.

## Security invariants (do not regress)

- All secrets in `.env` (gitignored, web-blocked by `.htaccess`); tokens stored hashed (reset tokens, remember-me validators, API keys); password reset bumps `users.session_version` and clears remember tokens.
- `.htaccess` denies `includes/`, `logs/`, `migrations/`, `cron/`, `vendor/`, dotfiles, `.sql/.log/.md/.lock` files; `uploads/.htaccess` disables script execution. Keep new sensitive paths behind these rules.
- Forms: `csrf_field()` + optional `honeypot_field()`; abuse-sensitive endpoints add `rate_limit()` (session) and `rate_limit_ip()` (file bucket).

## Conventions

- 4-space indent, single quotes in PHP, `snake_case` DB columns, terse inline JS in `(function(){...})()` wrappers (vanilla, no jQuery/CDN — a strict no-external-JS habit keeps the site shared-hosting fast).
- Tailwind utility classes inline; dark mode via `dark:` variants (class strategy, toggled by `assets/js/theme.js`).
- User-visible dynamic pages must keep working with an empty database (new install) — guard empty lists with friendly empty states.
