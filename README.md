# CARROT

A copyright-free sample groupware portal: Laravel 13 + Inertia v3 (React) + PostgreSQL.
The UI is Japanese throughout.

## Requirements

- PHP 8.4+, Composer
- Node 22+
- Docker (for the bundled PostgreSQL container)

## Setup

```bash
docker compose up -d          # PostgreSQL on 127.0.0.1:5432
composer setup                # install, .env, app key, migrate, seed, npm install, build
composer run dev              # serve + queue worker + vite + reverb + logs
```

`composer run dev` runs a worker on the `sandbox,default` queues. **It
matters**: script runs and notifications are queued jobs, so without it they
stay in `待機中` forever.

If 5432 is already in use on your machine, copy `.env.example` to `.env` first and
set `DB_PORT` to a free port (5433, say) - compose publishes the container on
whatever `DB_PORT` says, and Laravel connects to the same one.

`composer setup` is safe to run again: the seeders skip what is already there.
Open http://127.0.0.1:8000 and sign in.

| Login ID  | Role    | Password   |
| --------- | ------- | ---------- |
| `test`    | member  | `password` |
| `manager` | manager | `password` |
| `admin`   | admin   | `password` |

`/tools` starts empty, because a tool is something someone registers rather
than code in this repository. To see the platform with something in it:

```bash
php artisan demo:seed         # publish the demo catalog; --fresh to redo it
```

That adds `demo`, `demo-manager` and `demo-admin` (also `password`) and walks
a handful of sample tools through the real approval flow. See
[`demo/README.md`](demo/README.md) for what it publishes and how to add to it.

After pulling changes into an environment that is already set up:

```bash
php artisan migrate
php artisan db:seed --force
```

## What has to be set in `.env`

`composer setup` copies `.env.example` and the app runs as it is. These are the
values worth a second look:

| Key                                                   | Why                                                                                                                |
| ----------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| `DB_PORT`                                             | Compose publishes Postgres on it; change it when 5432 is taken                                                     |
| `SANDBOX_DRIVER`                                      | `none` only queues script runs. `bubblewrap` locally, `docker` on the runner host, or script tools never finish    |
| `REVERB_APP_ID` / `_KEY` / `_SECRET`, `VITE_REVERB_*` | Live updates. Leave blank and every screen falls back to polling - nothing breaks, it is just slower               |
| `CATALOG_DEPARTMENTS`                                 | The 所属 allowlist, comma separated. Blank means the field is free text                                            |
| `PASSKEYS_USER_HANDLE_SECRET`                         | Defaults to `APP_KEY`. Set it to its own fixed value if `APP_KEY` will ever be rotated, or passkeys stop resolving |
| `LOG_CHANNEL`                                         | The system screen tails whichever channel this names, so a `daily` or custom path is followed, not assumed         |

## Layout

| Path                                                        | What lives there                                       |
| ----------------------------------------------------------- | ------------------------------------------------------ |
| `routes/web.php`, `routes/settings.php`, `routes/tools.php` | Routes, split by area                                  |
| `app/Http/Controllers/Tools/`                               | The tool module                                        |
| `database/migrations/`                                      | `tools`, `tags`, `tag_tool` and `tool_submissions`     |
| `config/catalog.php`                                        | The 所属 list, from `CATALOG_DEPARTMENTS`              |
| `app/Sandbox/`                                              | The sandbox runners script tools execute in            |
| `config/sandbox.php`, `docker/sandbox/`                     | Sandbox limits, driver and container images            |
| `resources/js/pages/`                                       | Inertia page components                                |
| `demo/`                                                     | The demo catalog, published by `php artisan demo:seed` |
| `.ai/rules/`                                                | Decisions and traps worth knowing before editing       |

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
- **Notifications**: a submission messages every reviewer (inbox at `/inbox`,
  bell in the header) with a link to `/admin/approvals/{id}`; the decision
  messages the requester back. Reverb pushes updates live; screens also poll
  every minute as a safety net, so a dev box without `reverb:start` still works.
- **Roles**: `users.role` is `member`, `manager` or `admin`. The requester's
  department **manager** endorses first, then a system **admin** publishes. An
  admin may approve straight from the first stage, and a department with no
  manager falls through to the admins.

`/admin` covers every table without a database client:

| Screen             | What it edits                                                     |
| ------------------ | ----------------------------------------------------------------- |
| `/admin/approvals` | The two review stages (managers see their own department)         |
| `/admin/users`     | Roles and 所属 - the same columns as `php artisan carrot:promote` |
| `/admin/tools`     | Every row, deleted ones included: deprecate, restore, purge       |
| `/admin/tags`      | Rename and merge category tags                                    |
| `/admin/runs`      | Browse, delete, prune sandbox runs                                |
| `/admin/system`    | Queues, workers, sandbox, Reverb, recent runs, log tail           |

## The sandbox

Script tools never run inside the app. A queued `RunToolJob` on the `sandbox`
queue re-reads the approved source, checks its hash against the one the run
was requested with, and hands it to a `SandboxRunner`:

| `SANDBOX_DRIVER` | Where                    | Isolation                                                                                                                 |
| ---------------- | ------------------------ | ------------------------------------------------------------------------------------------------------------------------- |
| `docker`         | the runner host          | throwaway container: `--network none`, read-only root, uid 65534, all caps dropped, memory/cpu/pid limits, `timeout` kill |
| `bubblewrap`     | a dev box without Docker | fresh namespaces, no network, read-only root, private /tmp; memory via ulimit. Not for production                         |
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
2. Install rootless Docker for that account
   (`dockerd-rootless-setuptool.sh install`), then run
   `loginctl enable-linger carrot-runner` so its daemon survives logout.
   Never add the account to the `docker` group - a root dockerd socket is
   root, and `DockerSandboxRunner` refuses to start unless `docker info`
   reports rootless (`SANDBOX_REQUIRE_ROOTLESS=false` only on a dev box).
3. Enable cgroup v2 delegation for the user (`systemd` drop-in with
   `Delegate=cpu cpuset io memory pids`) or the `--memory`/`--cpus`/`--pids-limit`
   flags are ignored.
4. Build the images in CI (`docker/sandbox/README.md`) and pull them on the host;
   the runner never builds.
5. Set `SANDBOX_DRIVER=docker` and `DOCKER_HOST=unix:///run/user/<uid>/docker.sock`
   in the runner's `.env`; the web host keeps `SANDBOX_DRIVER=none`.

Locally, `composer run dev` runs a worker on `sandbox,default` and Reverb next to
the web server, so `SANDBOX_DRIVER=bubblewrap` (or `docker` with a rootless
daemon) makes script tools work end to end on one machine. Without Reverb the
screens simply poll.

## Checks

```bash
composer test        # Pint, PHPStan, Pest
npm run types:check  # tsc
npm run lint         # eslint
```
