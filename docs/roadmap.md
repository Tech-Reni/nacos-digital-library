# Rebuild roadmap

The original app (now in `legacy/`) was rebuilt from scratch on Laravel.
Work ships one PR at a time: open the PR, the owner tests it locally, merges,
and the item is ticked off here.

## Decisions so far

| Topic | Decision |
|---|---|
| Stack | Laravel 12, PHP 8.3, MySQL 8. No framework-free rewrite. |
| Hosting | DreamHost shared ("Web Hosting Growth"), user `new_nacos_admin`, PHP 8.3, SSH enabled. No Redis or long-running processes; database-backed session, cache and queue. |
| Cron | Not yet confirmed whether DreamHost allows every-minute cron. If not, run background work after the response is sent instead of relying on cron. The cron must run as `new_nacos_admin`. |
| Launch state | Not launched yet. No live data to migrate; the legacy app does not need patching. |
| Password reset | Both: email reset links (collect email at signup or next login) and one-time codes issued by an admin or course rep as a fallback. |
| Election results | Public and live during voting, for transparency. Serve a small static `live.json` snapshot rebuilt at most every few seconds; clients poll it with conditional requests (ETag/304), pause when the tab is hidden, and add jitter. No WebSockets/SSE on shared hosting. Leave a hook to switch to Pusher/Ably later. Show totals and turnout only; release updates in batches so individual votes cannot be inferred. |
| Ballot secrecy | Who voted (`election_voters`) and what was voted (`election_votes`, random UUIDv4 ids, no timestamps) are stored separately. One ballot per election covering every position; skipping a position is allowed. |
| Design | Keep the original green/yellow identity, improved: design tokens, dark mode, self-hosted subset fonts and icons, cheap GPU-friendly animations that respect reduced motion. |
| Repository | Work happens in the fork `Leommm-byte/nacos-digital-library`. When everything is done, one PR goes from the fork back to `Tech-Reni/nacos-digital-library`. |

## Open items for the owner

- Rotate the legacy database password (`nacos_db`). It was committed to git.
- Decide whether to delete the 22 scan images in `legacy/uploads/temp_scans/`
  (possibly real student documents) and whether to scrub them from history.

## PRs

- [ ] **1. Laravel foundation.** Schema, models, Docker stack, CI.
- [ ] **2. Design system and layout.** Tokens, dark mode, self-hosted assets, animation system, shared layout and navigation, Content-Security-Policy.
- [ ] **3. Authentication.** Signup, login, logout; rate limiting that cannot lock other people out; enforced suspension; roles and policies.
- [ ] **4. Password reset and MFA.** Email links plus admin/rep codes; email collection; TOTP with a locally generated QR code and hashed recovery codes; a code required to disable it.
- [ ] **5. Profile and account settings.**
- [ ] **6. Catalog.** Paging, full-text search and filters, book detail page, bookmarks, covers served through controllers.
- [ ] **7. Reader.** HTTP Range streaming, latest PDF.js, HiDPI rendering, prefetch, page jump, saved position, matric-number watermark.
- [ ] **8. Uploads.** Resumable uploads, OCR as a queued job with progress, virus scanning, per-user limits.
- [ ] **9. Moderation.** Reasons sent to uploaders, notifications, approvals history.
- [ ] **10. Dashboard and announcements**, with cached stats.
- [ ] **11. Elections.** Eligibility, single ballot enforced by the database, live public results as described above, audit trail.
- [ ] **12. Admin panel.** Users, bulk import with random temporary passwords and printable slips, books, reports, audit log viewer, settings in the database.
- [ ] **13. Assistant.** Keyword helper or real AI (to decide).
- [ ] **14. Launch polish.** Offline support, accessibility, performance budget, load testing.
- [ ] **15. Go live.** Deployment to DreamHost, backups, monitoring, launch checklist.

## Why the legacy app was slow (avoid repeating these)

- The reader downloaded whole PDFs before page 1 (no HTTP Range support).
- `ALTER TABLE` / `CREATE TABLE` ran on ordinary page requests.
- Around 14 sequential queries on the dashboard, `ORDER BY RAND()`, no paging,
  unindexable `LIKE '%…%'` search.
- Render-blocking Google Fonts and a CDN icon font on every page; hundreds of
  lines of inline, uncacheable CSS per page.
- A 416 KB PNG logo used as favicon and cover placeholder; no lazy loading.
- Election results polled every 3 s per student, each poll running several
  queries plus an `UPDATE`.
- PHP session locking serialised concurrent AJAX requests.
- Heavy `backdrop-filter` blur and shadows on mobile.
