---
paths:
  - 'app/Http/Controllers/Tool*'
---

# Controllers

## The tool catalog reads the tools table, never config
Tools are rows in `tools` (kind link/embed/script, status running/deprecated) with category tags in `tags` via the `tag_tool` pivot; department is a column on the tool. The hardcoded catalog and `config/tools.php` are gone.
Catalog tag groups are built server-side in ToolController (status first, values shown in English as stored). An embed tool is framed on its own page (tools/show, `embedUrl`), never on a screen of its own; the URL comes from Tool::frameableUrl(), which re-checks external-https per request so a bad row can never reach an iframe.
A tool is addressed by its ULID, never by its auto-increment id.
Deleted tools are soft-deleted: the catalog and route model binding never see them, so admin.tools.untrash / admin.tools.purge take a ULID and query onlyTrashed() themselves. Purging is a forceDelete - submissions and runs cascade with it.
