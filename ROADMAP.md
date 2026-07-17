# PuchoNow — Development Roadmap

What to build next, in order, and what each thing depends on. This is a working
document: when something ships, delete it from here. When something is
discovered, add it with its dependency.

The ordering rule is **dependency first, then leverage** — never enthusiasm.
A feature that needs an unapplied migration is not "nearly done"; it is blocked.

---

## Tier 0 — Blocked on the site owner. Nothing below this line matters more.

These cannot be done from the codebase. They are ordered by what is lost if
they are never done.

1. **Push to a git remote.** 36 commits exist on exactly one laptop, including
   five security fixes. There is no remote configured (`git remote -v` is
   empty). A disk failure erases all of it. Cost: about one minute.

2. **Apply migrations 029–033 in phpMyAdmin, in numeric order.** Five completed
   features are deployed and inert until this runs:
   | Migration | Unlocks | Consequence of waiting |
   |---|---|---|
   | `033_user_status` | Real bans | **Banned users can still log in** via passkey, OTP or social login |
   | `032_brute_force` | Login lockout | Password guessing is unthrottled beyond rate limits |
   | `031_soft_deletes` | Trash / restore | Admin deletes are permanent |
   | `030_email_otp` | `/login_otp` | Page shows an honest "unavailable" state |
   | `029_short_links` | `/s/<code>` sharing | Share links fall back to full URLs |
   The code tolerates all five being absent (readiness shims), so nothing is
   broken while waiting — the features simply do not exist yet.

3. **Schedule the five crons in hPanel** (`cron/` is CLI-only by design):
   `backup_db.php` (daily — this is your only backup), `publish_scheduled.php`
   (every 15m — scheduled blog posts do not publish without it),
   `resolve_bounties.php` (hourly), `tag_digest.php` (weekly),
   `question_of_the_day.php` (daily).
   Note `backup_db.php` is the one that matters: right now there is no backup.

---

## Tier 1 — The real bottleneck, measured.

**The site contains one question.**

Measured live on 2026-07-17, not estimated:
| Metric | Count |
|---|---|
| Questions in the sitemap | **1** |
| Question links on `/questions` | **1** |
| Categories with any content | **1** of 711 |
| Features on `/features` | 294 |
| Public pages | 80 |
| Admin pages | 44 |

This is the whole roadmap. Everything below Tier 1 is a rounding error against
it. A Q&A site with one question is worth nothing to its first visitor, and the
first visitor decides whether there is a second. No feature fixes this; 294 did
not, and 470 would not.

The highest-leverage work available, by a wide margin:
- **20–50 real questions with real answers**, in 3–5 categories you can speak to.
- Choose those categories by what you can actually answer, not by search volume.
- Everything else waits.

This is not a coding task, which is precisely why it keeps losing to coding
tasks. Building is the comfortable move; it is not the useful one right now.

### 1.1 Thin content — already solved, do not rebuild

Recorded because it looked like the top priority and measurement said otherwise.

Two mechanisms already exist and work, verified live:
- `sitemap.php` JOINs categories to questions, so only categories with content
  are ever submitted. The live sitemap lists **1** category, not 711.
- `questions.php` sets `noindex, follow` on empty listings — confirmed live on
  `/c/3d-animation`, `/c/accountancy`, `/c/ahmedabad`.

So the 710 empty categories are neither indexed nor advertised. There is no
crawl-budget fire. The only refinement worth having was raising the bar from
"non-empty" to "has ≥3 questions" (`THIN_LISTING_MIN_QUESTIONS`), which is now
in place as prevention for when content grows — not as a fix for a live problem.

---

## Tier 2 — Buildable now, no migration, no decision needed.

Ordered by value per hour. All of it is worth less than one afternoon of writing
questions — see Tier 1.

1. **Admin grid cards** (requested) — card layout for the admin link surfaces.
   `admin/` currently has 44 pages reachable mainly via sidebar; a grouped grid
   makes the surface navigable. Pure UI, low risk.
3. **Video embeds, hardening + coverage** — `md_video_embed()` already extracts
   the video ID and rebuilds the iframe from a hardcoded template (the safe
   pattern). Extend provider coverage inside that same pattern. Never
   interpolate a user-supplied URL into `src`.
4. **Storage: usage dashboard on real numbers** — the cost estimator exists;
   feed it actual `uploads/` totals so the estimate reflects this site rather
   than a model.

---

## Tier 3 — Buildable, but each needs one decision from you.

Do not start these speculatively. Each is one complete module.

- **Dropbox / OneDrive / Google Drive storage.** Each needs its own OAuth app
  registration (which only you can create), redirect URI, consent screen, and
  token refresh loop. They do not batch — one module each, roughly a session
  apiece. **Name the one you would actually use.** Note the honest position:
  the S3 driver already covers six providers including R2 (no egress fees) and
  B2 (cheapest storage). A consumer-cloud driver is a convenience, not a gap.
- **Social share from admin via API.** "Share to X/LinkedIn/Telegram from the
  admin panel" needs a developer app + OAuth token per network. Telegram is
  already done (bot API, no OAuth). X and LinkedIn each need an app you must
  register, and both have restrictive free tiers. Worth confirming the free tier
  still permits posting before either is built.

---

## Not doing, and why

Kept here so it is not re-proposed each session.

- **Terabox** — no public server-side API exists.
- **Mega.nz** — client-side encryption by design; no server-side PHP SDK. A
  driver would have to hold user keys, which defeats the product.
- **AI / paid APIs** — explicitly out of scope by owner instruction.
- **Framework migration** (Laravel/Node/React/Vue/Next) — explicitly forbidden;
  the whole architecture assumes zero-build shared hosting.
- **"470+ features" as a target.** The count is not the product. `features.php`
  computes its headline from the actual list precisely so the number cannot
  drift from reality. Every entry there is real today; that property is worth
  more than any larger number that is not.

---

## How to use this file

Work top-down. If an item in Tier 0 is open, that item is the most valuable
thing available — a security fix that has not run is not a security fix, and
code that exists in one place does not exist.
