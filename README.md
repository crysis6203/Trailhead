# Trailhead

Queue-and-sync companion to Scoutbook Plus for Troop 911. Sign off scout
requirements from your phone during a meeting; a scheduled Claude Cowork
task pushes the queue into Scoutbook and pulls fresh roster/requirement
data back, on a Monday-night cadence.

This is a clean rebuild — no relation to (and no shared database with) any
previous version of this project.

## How it fits together

- **This app** (PHP/MySQL, deployed here on Cloudways) is the queue and the
  local mirror of Scoutbook's roster/requirements. It has no Scoutbook
  login of its own and never talks to Scoutbook directly.
- **Claude Cowork** is the only thing that ever logs into Scoutbook. On a
  schedule (Monday 10:00pm CT) it:
  1. Logs into Scoutbook (via LastPass autofill) and selects the
     Troop 911 — Scoutmaster context.
  2. Calls `GET /api/push-queue.php` for this app's pending queue, grouped
     by requirement.
  3. Uses Scoutbook's Quick Entry screens to record each group, then calls
     `PATCH /api/signoffs.php?id=...` to mark each `confirmed` or `flagged`.
  4. Scrapes the current roster and requirement/progress data and POSTs it
     to `POST /api/sync.php`.
  5. Logs the run via `POST /api/sync-runs.php`.

## Requirements

- PHP 8.1+ with the `pdo_mysql` extension (standard on Cloudways PHP apps)
- MySQL 5.7+ / MariaDB 10.3+

## Deploying on Cloudways

1. **Create a new PHP application** in Cloudways (not a WordPress/Laravel
   template — plain PHP). Note the app's public folder; Cloudways typically
   expects the webroot to be `public_html` — either set the app's document
   root to this repo's `public/` folder, or symlink/copy `public/*` into
   `public_html` and keep `src/`, `migrations/`, and `.env` one level above
   the web root so they aren't publicly served.
2. **Create a MySQL database** for the app (a fresh one — do not reuse an
   old Trailhead database). Note the host, name, user, and password from
   Cloudways' database management screen.
3. **Copy `.env.example` to `.env`** in the app's root (one level above
   `public/`) and fill in:
   - `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` from step 2
   - `API_KEY` — generate a long random value, e.g. `openssl rand -hex 32`
4. **Run the migration** against the new database:
   ```
   mysql -h <DB_HOST> -u <DB_USER> -p <DB_NAME> < migrations/001_initial_schema.sql
   ```
   (Cloudways' phpMyAdmin/DB Manager can also run this file directly if you
   don't have shell/mysql-client access.)
5. **Visit the app's URL.** The frontend will prompt once for the API key
   (the same value you put in `.env`) and store it in `localStorage` on
   that device.
6. **First pull**: before the queue/capture screens are useful, Scoutbook's
   real roster and requirement data need to land in the database at least
   once via `POST /api/sync.php` — this happens automatically once the
   Cowork scheduled task is set up (see below), or can be triggered by hand
   for initial testing.

## Setting up the Cowork scheduled task

Create a Cowork scheduled task (Monday, 10:00pm CT, local — browser/computer
use needs the desktop app open) with instructions along these lines:

1. Open Chrome, navigate to Scoutbook Plus, log in via LastPass autofill.
2. Confirm/select "Troop 911 — Scoutmaster" under "Currently viewing." If
   this can't be confirmed, abort and log a failed run via
   `POST /api/sync-runs.php` with a clear `fail_reason`.
3. `GET {APP_URL}/api/push-queue.php` (with `X-API-Key` header).
4. For each group returned: open the matching Unit Quick Entry screen
   (Ranks or Merit Badges), select the requirement/version, check the
   listed scouts (match by `memberId`, never by name), check the
   requirement, fill Comment/Completion Date from the payload, submit.
5. For each scout in the group, `PATCH {APP_URL}/api/signoffs.php?id=<signoffId>`
   with `{"status": "confirmed"}` on success, or `{"status": "flagged",
   "flag_reason": "..."}` if the scout/requirement couldn't be matched.
6. Regardless of push outcome, scrape the current roster and
   requirement/progress data and `POST {APP_URL}/api/sync.php`.
7. `POST {APP_URL}/api/sync-runs.php` to log the run's final counts and
   status (start this call's counterpart — the "create" call with no `id`
   — at step 1, before login, so a hard failure at step 2 still gets logged).

Every request needs the header `X-API-Key: <the same value as .env's API_KEY>`.

## Local development

```
# One-time: create a database and run the migration
mysql -u root -e "CREATE DATABASE trailhead;"
mysql -u root trailhead < migrations/001_initial_schema.sql

cp .env.example .env   # fill in local DB credentials + an API key

cd public && php -S 127.0.0.1:8888
```

Then visit `http://127.0.0.1:8888`.

## Project layout

```
migrations/          SQL migrations (run manually, in order)
src/                 Shared PHP (DB connection, auth) — not web-accessible
public/              Web root
  index.php          Frontend shell
  app.js / app.css   Frontend logic + styles (vanilla JS, no build step)
  api/               REST endpoints, one file per resource
```

## Data model notes

- `signoffs` is the only table the push touches. `scouts`,
  `rank_requirements`, `merit_badge_requirements`,
  `scout_rank_progress`, and `scout_merit_badge_progress` are the only
  tables the pull touches. This separation is enforced by the code, not
  just convention — `sync.php` (the pull endpoint) has no code path that
  can reach the `signoffs` table.
- A signoff's `completion_date` defaults to the day it was added to the
  queue, not the day the sync job happens to run, and can be overridden.
- Every signoff is implicitly "Mark as Approved" in Scoutbook — there's no
  recorded-but-unapproved state, since a signoff in this app already
  represents Chris's approval as Scoutmaster.
