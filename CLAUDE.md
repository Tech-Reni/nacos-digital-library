# Project guide

NACOS YabaTech student portal, being rebuilt on Laravel 12 (PHP 8.3, MySQL 8)
for DreamHost shared hosting. Read `docs/roadmap.md` first: it holds the plan,
the decisions already made with the owner, and PR status.

- `legacy/` is the original app, kept only as a reference. Never serve or
  extend it.
- Before pushing, run `vendor/bin/pint`, `vendor/bin/phpstan analyse` and
  `php artisan test`. CI runs the same checks, with tests on MySQL 8.
- Local stack: `docker compose up -d --build` (app on :8080, Mailpit on :8025).
- Shared hosting constraints: no Redis, no long-running workers, no
  WebSockets. Use the database drivers and the cron-driven scheduler.
- Models are strict outside production; fix N+1 queries with eager loading
  instead of relaxing strict mode.
- Do not add links between `election_voters` and `election_votes`.
