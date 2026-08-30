# The demo catalog

`/tools` ships empty on purpose - a tool is a row someone registers, not code
in this repository. This directory is the exception: a small, obviously fake
catalog so the platform can be shown working.

```bash
php artisan demo:seed            # publish what demo/tools.php describes
php artisan demo:seed --fresh    # delete the demo data and publish it again
php artisan demo:seed --clear    # delete the demo data and stop
```

`--clear` also removes the three demo accounts, since they carry a documented
password. Tools nobody seeded are left alone. Both `--fresh` and `--clear`
take the inbox messages and bell notifications with them - they are what the
demo raised, and nothing else links them to the rows they announce.

It refuses to run in production without `--force`.

## What it does

Nothing is written into `tools` directly. The command files a submission as
the demo requester, has the demo department manager endorse it and a demo
admin publish it - the three steps a real tool goes through - so every demo
tool ends up with a genuine version stamp, approval history and inbox message.

The requests in the same file are seeded the same way, through the actions the
triage screen uses. One tool is filed against one of them, so the demo also
shows what closes a request: the tool that answers it going live, not somebody
ticking it off.

Three accounts are created or reset each run, all with password `password`:

| Login          | Role                           | Why                                |
| -------------- | ------------------------------ | ---------------------------------- |
| `demo`         | member                         | files the requests, owns the tools |
| `demo-manager` | manager of the demo department | the first approval stage           |
| `demo-admin`   | admin                          | publishes, and can see `/admin`    |

Messages and bell notifications are queued, so they arrive once a worker is
running (`composer run dev` starts one).

## Adding a request

Add an entry to the `requests` list and re-run with `--fresh`. The title is
how the command recognises it on a later run:

```php
[
    'title' => 'デモ: 何かしたい',
    'body' => "今どうしているか。\n何が大変か。",
    'categories' => ['ポータル'],
    'desired_kind' => 'script',      // optional: link | embed | script
    'needed_by' => '+3 weeks',       // optional, relative so it never goes stale
    'state' => 'open',               // open | accepted | in_progress
],
```

There is no `delivered` state to set. A request reaches it by being answered:
name it from a tool's `answers` key and approving that tool closes it.

## Adding a tool

Add an entry to the `tools` list and re-run with `--fresh`. An entry is a
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
    'answers' => 'デモ: 何かしたい',   // optional: a request this tool closes
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
