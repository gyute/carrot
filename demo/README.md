# The demo catalog

`/tools` ships empty on purpose - a tool is a row someone registers, not code
in this repository. This directory is the exception: a small, obviously fake
catalog so the platform can be shown working.

```bash
php artisan demo:seed            # publish what demo/tools.php describes
php artisan demo:seed --fresh    # delete the demo tools and publish again
```

It refuses to run in production without `--force`.

## What it does

Nothing is written into `tools` directly. The command files a submission as
the demo requester, has the demo department manager endorse it and a demo
admin publish it - the three steps a real tool goes through - so every demo
tool ends up with a genuine version stamp, approval history and inbox message.

Three accounts are created or reset each run, all with password `password`:

| Login          | Role                           | Why                                |
| -------------- | ------------------------------ | ---------------------------------- |
| `demo`         | member                         | files the requests, owns the tools |
| `demo-manager` | manager of the demo department | the first approval stage           |
| `demo-admin`   | admin                          | publishes, and can see `/admin`    |

Messages and bell notifications are queued, so they arrive once a worker is
running (`composer run dev` starts one).

## Adding a tool

Add an entry to `demo/tools.php` and re-run with `--fresh`. An entry is a
submission payload plus a `state`:

```php
[
    'kind' => 'link',                // link | embed | script
    'name' => 'デモ: 何か',           // how the command recognises it later
    'summary' => '一行の説明。',
    'description' => null,           // optional, shown on the tool's page
    'icon' => 'file-text',           // Tool::ICONS
    'accent' => 'amber',             // Tool::ACCENTS
    'categories' => ['ポータル'],
    'config' => ['url' => '/tools'],
    'source' => 'scripts/hello.php', // script only; a real file under demo/
    'state' => 'published',          // 'pending' leaves it for review instead
],
```

## Rules the demo has to respect

- **An embed tool cannot frame this portal.** `Tool::frameableUrl()` takes an
  external `https` origin only and refuses our own host, because an embedded
  page on our origin would get our DOM. A page served from this app can be a
  `link` target, never an `embed` one.
- **No real host, ever.** This directory is the first place someone will copy
  from, so every URL here is a documentation domain or a portal path.
- With `CATALOG_DEPARTMENTS` set, the forms only offer those departments. The
  demo writes its own onto the submission either way, but add it to the list
  to edit a demo tool from the screens.
