# CARROT

A copyright-free sample groupware portal: Laravel 13 + Inertia v3 (React) + PostgreSQL.
The UI is Japanese throughout.

## Requirements

- PHP 8.4+, Composer
- Node 20+
- Docker (for the bundled PostgreSQL container)

## Setup

```bash
docker compose up -d          # PostgreSQL on 127.0.0.1:5432
composer setup                # install, .env, app key, migrate, seed, npm install, build
composer run dev              # serve + queue worker + vite + logs
```

If 5432 is already in use on your machine, copy `.env.example` to `.env` first and
set `DB_PORT` to a free port (5433, say) - compose publishes the container on
whatever `DB_PORT` says, and Laravel connects to the same one.

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

`composer run dev` starts the PHP server, a worker on the `sandbox,default`
queues, Vite and the log tail. **The worker matters**: script tool runs are
queued jobs, so without it they stay in `待機中` forever.

## Layout

| Path                                                        | What lives there                                           |
| ----------------------------------------------------------- | ---------------------------------------------------------- |
| `routes/web.php`, `routes/settings.php`, `routes/tools.php` | Routes, split by area                                      |
| `app/Http/Controllers/Tools/`                               | The tool module                                            |
| `database/migrations/`                                      | `tools`, `tags`, `tag_tool` and `tool_submissions`          |
| `config/catalog.php`                                        | The 所属 list, from `CATALOG_DEPARTMENTS`                   |
| `app/Sandbox/`                                              | The sandbox runners script tools execute in                |
| `config/sandbox.php`, `docker/sandbox/`                     | Sandbox limits, driver and container images                |
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
  draft → pending → endorsed → approved / rejected. Display fields - name, summary,
  description, icon, tags - are edited in place by the owner without review.
- **Versions**: every approval stamps the tool with the approval date to the
  minute (`202608271037`, then `202608271037.2` for a second approval within
  the same minute) and records who requested, endorsed and approved it. The
  approved submissions are the history.
- **Roles**: `users.role` is `member`, `manager` or `admin`. Approval has two
  stages: the requester's department **manager** endorses first, then a system
  **admin** confirms and publishes (an admin may also approve straight from the
  first stage; a department with no manager falls through to the admins).
  Admins may deprecate, restore or delete a tool directly. Set roles from the
  shell: `php artisan carrot:promote <username> --role=manager --department=開発`,
  `--role=admin`, `--revoke`. The seeder creates `manager` / `admin` (password
  `password`) for trying this locally.

## The sandbox

Script tools never run inside the app. A queued `RunToolJob` on the `sandbox`
queue re-reads the approved source, checks its hash against the one the run
was requested with, and hands it to a `SandboxRunner`:

| `SANDBOX_DRIVER` | Where                    | Isolation                                                                                                                 |
| ---------------- | ------------------------ | ------------------------------------------------------------------------------------------------------------------------- |
| `docker`         | the runner host          | throwaway container: `--network none`, read-only root, uid 65534, all caps dropped, memory/cpu/pid limits, `timeout` kill |
| `bubblewrap`     | a dev box without Docker | fresh namespaces, no network, read-only root, private /tmp; memory via ulimit. Not for production                          |
| `fake`           | tests                    | never executes anything                                                                                                   |
| `none`           | the web host             | only queues runs; throws if a job ever executes here                                                                      |

A script tool declares whether it needs the internet (`config.network`:
`none`, the default, or `internet`). Reviewers see the choice highlighted on the
approval screen and can run the submitted script in the sandbox before deciding;
the runner attaches an `internet` tool to `SANDBOX_INTERNET_NETWORK` (default
`bridge` - point it at a bridge whose egress you control) and everything else to
`--network none`.

Inputs reach the script as a JSON file named by `$TOOL_INPUTS`; stdout is the
result, capped at `SANDBOX_OUTPUT_BYTES`. Runs are rate limited per user
(`SANDBOX_RATE_LIMIT` per minute) and pruned after `SANDBOX_RUN_RETENTION_DAYS`
by the scheduled `carrot:prune-runs`.

### Runner host

Run the same codebase on a separate host that serves no HTTP and only works the
queues: `php artisan queue:work --queue=sandbox,default`. It needs the database,
queue and storage credentials and nothing else.

1. Create an unprivileged account, e.g. `carrot-runner`, with `/etc/subuid` and
   `/etc/subgid` ranges.
2. Install rootless Docker for that account (`dockerd-rootless-setuptool.sh
   install`) and `loginctl enable-linger carrot-runner` so its daemon survives
   logout. Never add the account to the `docker` group - a root dockerd socket is
   root, and `DockerSandboxRunner` refuses to start unless `docker info` reports
   rootless (`SANDBOX_REQUIRE_ROOTLESS=false` only on a development box).
3. Enable cgroup v2 delegation for the user (`systemd` drop-in with
   `Delegate=cpu cpuset io memory pids`) or the `--memory`/`--cpus`/`--pids-limit`
   flags are ignored.
4. Build the images in CI (`docker/sandbox/README.md`) and pull them on the host;
   the runner never builds.
5. Set `SANDBOX_DRIVER=docker` and `DOCKER_HOST=unix:///run/user/<uid>/docker.sock`
   in the runner's `.env`; the web host keeps `SANDBOX_DRIVER=none`.

Locally, `composer run dev` runs a worker on `sandbox,default` next to the web
server, so `SANDBOX_DRIVER=bubblewrap` (or `docker` with a rootless daemon)
makes script tools work end to end on one machine.

## Checks

```bash
composer test        # Pint, PHPStan, Pest
npm run types:check  # tsc
npm run lint         # eslint
```
