# NACOS YabaTech Digital Library

Student portal for NACOS YabaTech: digital library and reader, uploads with
OCR, moderation, announcements and executive elections.

Built with **Laravel 12 / PHP 8.3 / MySQL 8**, designed to run on shared
hosting (DreamHost) with no Redis or long-running workers.

> The original app is kept in [`legacy/`](legacy) as a reference while it is
> rebuilt feature by feature. It is not served and will be deleted once the
> rebuild is complete.

## Run it locally

### Option A: Docker (recommended)

Needs only [Docker Desktop](https://www.docker.com/products/docker-desktop/).

```bash
git clone https://github.com/Tech-Reni/nacos-digital-library.git
cd nacos-digital-library
docker compose up -d --build
```

The first start takes a few minutes (it builds the image and installs
dependencies). After that, open:

| What | Where |
|---|---|
| App | http://localhost:8080 |
| Mailpit (every email the app sends) | http://localhost:8025 |
| MySQL | `localhost:33060`, user `nacos`, password `secret` |

The container creates `.env`, generates the app key, runs migrations and
seeds demo data automatically. Useful commands:

```bash
docker compose logs -f app                          # follow logs
docker compose exec app php artisan test            # run the test suite
docker compose exec app php artisan migrate:fresh --seed   # reset the database
docker compose down                                 # stop (data is kept)
docker compose down -v                              # stop and wipe the database
```

### Option B: Without Docker

Needs PHP 8.3 (with `pdo_mysql`, `intl`, `gd`, `zip`), Composer and MySQL 8.

```bash
composer install
cp .env.example .env          # then set DB_* to your local MySQL
php artisan key:generate
php artisan migrate --seed
php artisan serve             # http://localhost:8000
```

### Demo accounts

Seeded in local/testing environments only, never in production. All use the
password **`Password1!`**.

| Role | Matric number |
|---|---|
| Admin | `F/HD/21/0000001` |
| Governor | `F/HD/22/0000002` |
| Course Rep | `F/ND/23/0000003` |
| Student | `F/ND/24/0000004` |

## Checks

Every pull request runs these in CI. Run them locally before pushing:

```bash
vendor/bin/pint            # format code (use --test to only check)
vendor/bin/phpstan analyse # static analysis
php artisan test           # test suite
```

## Architecture notes

- **Shared-hosting friendly:** sessions, cache and the job queue all use the
  database. The production cron runs `php artisan schedule:run` every minute,
  which also drains queued jobs (OCR, notifications).
- **Private files:** book PDFs and covers live on the `private` disk
  (`storage/app/private/library`) and are only ever streamed through
  controllers that check permissions. Nothing under `storage/` is
  web-accessible.
- **Strict models:** outside production, lazy loading (N+1 queries),
  mass-assigning unknown fields and reading missing attributes all throw, so
  performance and data bugs surface during development.
- **Elections:** who voted (`election_voters`) and what was voted
  (`election_votes`) are stored separately with no link between them, so
  ballots stay secret even to admins with database access.
- Times are stored in UTC and displayed in `Africa/Lagos`.

## Deployment

See [docs/deployment.md](docs/deployment.md).
