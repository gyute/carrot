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
Vite and the log tail.

## Layout

| Path                                                        | What lives there                                           |
| ----------------------------------------------------------- | ---------------------------------------------------------- |
| `routes/web.php`, `routes/settings.php`, `routes/tools.php` | Routes, split by area                                      |
| `app/Http/Controllers/Tools/`                               | The tool module                                            |
| `database/migrations/`                                      | `tools`, `tags`, `tag_tool` and `tool_submissions`          |
| `config/catalog.php`                                        | The 所属 list, from `CATALOG_DEPARTMENTS`                   |
| `resources/js/pages/`                                       | Inertia page components                                    |
| `.ai/rules/`                                                | Decisions and traps worth knowing before editing           |

## The tool module

`/tools` collects the in-house tools. Nothing is pushed as code - a tool is a
row in the `tools` table.

- **Kinds**: `link` opens a URL (external https or a portal path), `embed`
  frames an external https page inside the tool's own screen, `script` runs a
  script in the sandbox.
- The catalog filters on status, category and 所属; deprecated tools stay for
  reference but are hidden until their status is ticked.
- **Requests** (`tool_submissions`): registering a tool and changing what it
  does (URL / script / runtime / inputs) or retiring it go through
  draft → pending → approved / rejected. Display fields - name, summary,
  description, icon, tags - are edited in place by the owner without review.
- **Roles**: `users.role` is `member`, `manager` or `admin`. Set roles from the
  shell: `php artisan carrot:promote <username> --role=manager --department=開発`,
  `--role=admin`, `--revoke`. The seeder creates `manager` / `admin` (password
  `password`) for trying this locally.

## Checks

```bash
composer test        # Pint, PHPStan, Pest
npm run types:check  # tsc
npm run lint         # eslint
```
