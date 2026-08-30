# The demo catalog

`/tools` ships empty on purpose - a tool is a row someone registers, not code
in this repository. This directory is the exception: a small, obviously fake
catalog so the platform can be shown working.

```bash
php artisan demo:seed            # publish what demo/tools.php describes
php artisan demo:seed --fresh    # delete the demo tools and publish again
```

It refuses to run in production unless you pass `--force`.

## What it does

Nothing is written into `tools` directly. The command files a submission as
the demo requester, has the demo department manager endorse it and a demo
admin publish it - the same three steps a real tool goes through. So the demo
also demonstrates: every tool ends up with a real version stamp, a real
approval history and real inbox messages.

It creates three accounts if they are missing (password `password`):

| Login | Role | Why |
| --- | --- | --- |
| `demo` | member | files the requests, owns the tools |
| `demo-manager` | manager of the demo department | the first approval stage |
| `demo-admin` | admin | publishes, and can see `/admin` |

Messages and bell notifications are queued, so they arrive once a worker is
running (`composer run dev` starts one).

## Adding a tool

Add an entry to `demo/tools.php` and re-run with `--fresh`. An entry is a
submission payload plus a `state`:

```php
[
    'kind' => 'link',              // link | embed | script
    'name' => 'デモ: 何か',         // also how the command recognises it later
    'summary' => '一行の説明。',
    'description' => null,          // optional, shown on the tool's page
    'icon' => 'file-text',          // Tool::ICONS
    'accent' => 'amber',            // Tool::ACCENTS
    'categories' => ['ポータル'],
    'config' => ['url' => '/tools'],
    'source' => 'scripts/hello.php', // script tools only, path under demo/
    'state' => 'published',         // or 'pending' to leave it for review
],
```

A `state` of `pending` stops after the submission, which is how the approval
screens get something to show.

Script sources live in `demo/scripts/` as real files, so they can be edited
and linted like any other script.

## Rules the demo has to respect

- **An embed tool cannot frame this portal.** `Tool::frameableUrl()` only
  accepts an external `https` origin and refuses our own host, because an
  embedded page on our origin would get our DOM. So a page served from this
  app can be a `link` target, never an `embed` one - the embed entries use
  documentation domains (`example.com`, `rfc-editor.org`).
- **No real host, ever.** Everything here is a documentation domain or a path
  inside the portal. This directory is the first place someone will copy from,
  so it must not contain anything a real deployment uses.
- If `CATALOG_DEPARTMENTS` is set, the forms only offer those departments.
  The demo writes its own department straight onto the submission, so it works
  either way - but add it to the list if you want to edit a demo tool from
  the screens.
