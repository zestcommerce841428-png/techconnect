# PuchoNow

A full-featured Q&A community platform — **[puchonow.in](https://puchonow.in)** — built in plain PHP 8 + MySQL + Tailwind CSS, designed to run fast on inexpensive shared hosting (Hostinger). 275+ shipped features: see [/features](https://puchonow.in/features).

No framework, no server-side build step, no paid APIs. Everything is dependency-light on purpose.

## Stack

| Layer | Choice | Why |
|---|---|---|
| Backend | Plain PHP 8.3, PDO/MySQL | Runs anywhere, zero framework lock-in |
| Frontend | Tailwind CSS (compiled + committed), vanilla JS (esbuild-minified) | No CDN dependencies, works offline |
| Email | PHPMailer via SMTP | |
| Auth extras | TOTP 2FA (robthree), Passkeys/WebAuthn (web-auth) | |
| Payments | Stripe / Razorpay / PayPal, signature-verified webhooks | |
| Hosting | Shared hosting (Apache + `.htaccess`) | ~₹800/yr total cost |

## Local development

```bash
# 1. Requirements: PHP 8.2+ (with GD, curl, pdo_mysql), MySQL, Node 18+
cp config.sample.php config.php        # then create .env with DB_/SMTP_/SITE_ vars (see includes/env.php)
mysql -u root yourdb < schema.sql      # base schema
for f in migrations/*.sql; do mysql -u root yourdb < "$f"; done   # then all migrations, in order

npm install && npm run build           # compile Tailwind CSS + minify JS
php -S localhost:8000 router.php       # router.php mirrors production .htaccess rewrites
```

## Deploying (shared hosting)

1. `npm run build` locally — the server has no Node; compiled assets are committed.
2. Upload changed files via FTP/File Manager to the site's `public_html`.
3. New migration? Run its SQL in phpMyAdmin (never edit an already-applied migration — add a new numbered file).
4. Crons (hPanel → Cron Jobs, all CLI): `cron/backup_db.php` (daily), `cron/publish_scheduled.php` (hourly), `cron/resolve_bounties.php` (daily), `cron/tag_digest.php` (weekly), `cron/question_of_the_day.php` (daily, posts to Telegram).

## Repository map

```
*.php                 One file per public page (clean URLs via .htaccess rewrite)
includes/             Shared subsystems: auth, db, mailer, payments/, oauth/, markdown, …
admin/                Admin & moderator panel (role-gated in admin/includes/admin_header.php)
api/                  Session+CSRF AJAX endpoints  ·  api/v1/  key-authenticated REST API
assets/js/            Source JS  →  assets/dist/js/  minified output (what pages load)
migrations/           Numbered SQL migrations, applied manually in order
cron/                 CLI-only scheduled scripts
schema.sql            Base schema (new installs)
CLAUDE.md             Architecture & conventions guide (for humans AND AI assistants)
```

## Architecture in five sentences

Every page `require`s `includes/auth.php`, which boots the session, security layers (CSRF, rate limits, IP blocks, honeypots), and the HTML-minifying output buffer. Pages follow POST-redirect-GET: handle the form at the top, query data, then render between `header.php`/`footer.php`. All SQL goes through the `db()` PDO singleton with prepared statements; all output goes through `e()`. Runtime configuration lives in the `site_settings` DB table (`setting()` helper), secrets in `.env`. The REST API authenticates with hashed bearer keys; internal AJAX uses the session.

For the full picture — conventions, security invariants, notification/payment/migration patterns — read **[CLAUDE.md](CLAUDE.md)**. It is written to make any developer (or AI assistant) productive quickly; keep it updated when architecture changes.

## Security highlights

CSRF on every form · prepared statements everywhere · hashed tokens at rest (reset, remember-me, API keys) · session versioning (password reset logs out all devices) · TOTP 2FA + passkeys · IP + session rate limiting · honeypots · signed payment webhooks · security headers + HSTS · script execution disabled in uploads · `security.txt`

## License

Proprietary — all rights reserved.
