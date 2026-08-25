# CARROT

A copyright-free sample groupware portal: Laravel 13 + Inertia v3 (React) + PostgreSQL.
The UI is Japanese throughout.

## Requirements

- PHP 8.3+, Composer
- Node 20+
- Docker (for the bundled PostgreSQL container)

## Setup

```bash
docker compose up -d          # PostgreSQL on 127.0.0.1:5432
composer setup                # install, .env, app key, migrate, seed, npm install, build
composer run dev              # serve + queue worker + vite + logs
```

`composer setup` is safe to run again: the seeders skip what is already there.
Open http://127.0.0.1:8000 and sign in with the seeded account.

| Login ID | Password   |
| -------- | ---------- |
| `test`   | `password` |

After pulling changes into an environment that is already set up:

```bash
php artisan migrate
php artisan db:seed --force
```

## Running it

`composer run dev` starts four processes at once - the PHP server, `queue:listen`,
Vite and the log tail. **The queue worker matters**: the tool module's CSV exports
run as queued jobs, so without a worker they stay in `待機中` forever.

## Layout

| Path                                                        | What lives there                                           |
| ----------------------------------------------------------- | ---------------------------------------------------------- |
| `routes/web.php`, `routes/settings.php`, `routes/tools.php` | Routes, split by area                                      |
| `app/Http/Controllers/Tools/`                               | The tool module                                            |
| `config/exports.php`                                        | Export definitions - the reviewed SQL each CSV export runs |
| `config/tools.php`                                          | Pages the studio tool is allowed to frame                  |
| `resources/js/pages/`                                       | Inertia page components                                    |
| `.ai/rules/`                                                | Decisions and traps worth knowing before editing           |

## The tool module

`/tools` collects the in-house tools.

- **日次アクセスログ** queues a CSV export. The batch list at `/tools/exports/jobs`
  shows progress; a finished file is downloaded by its owner, or by anyone holding
  the download code issued when the batch was requested. Files expire after
  `EXPORT_RETENTION_DAYS` days.
- **スタジオ** frames an external page listed in `config/tools.php`.

Exports read through the `warehouse` database connection. It falls back to the
application database, so the demo runs with no extra configuration; point
`WAREHOUSE_DB_*` at RDS with a read-only role for anything real.

## Checks

```bash
composer test        # Pint, PHPStan, Pest
npm run types:check  # tsc
npm run lint         # eslint
```
