---
paths:
  - 'app/Http/Controllers/Tool*'
---

# Controllers

## The tool catalog reads the tools table, never config
Tools are rows in `tools` (kind link/embed/script, status running/deprecated) with category tags in `tags` via the `tag_tool` pivot; department is a column on the tool. Nothing about the catalog is hardcoded or read from config.
Catalog tag groups are built server-side in ToolController (status first, values shown in English as stored). An embed tool is framed on its own page (tools/show, `embedUrl`), never on a screen of its own; the URL comes from Tool::frameableUrl(), which re-checks external-https per request so a bad row can never reach an iframe.
A tool is addressed by its ULID, never by its auto-increment id.
Deleted tools are soft-deleted: the catalog and route model binding never see them, so admin.tools.untrash / admin.tools.purge take a ULID and query onlyTrashed() themselves. Purging is a forceDelete - submissions and runs cascade with it.

## The catalog filter shows everything it does
The status filter opens with every status but `deprecated` ticked, computed on the client from the options the server sent. There is no hidden rule left: what is ticked is the whole truth, and the earlier `statusFiltered || isShownByDefault` special case was removed because a filter that hides rows while showing no ticks is unreadable.
`users.catalog_filters` (jsonb, nullable) holds what a person kept via 「この絞り込みを既定にする」. Null means never saved and falls back to that default; `[]` means they saved "show everything" - keep the two apart.
ToolController::savedFilters() drops saved values that no longer appear in the tag groups. A category can be renamed or merged, and a filter naming a tag nobody has would otherwise hide the whole catalog. It drops them from the response only; the stored value is left alone, so a value comes back if its tag does.
Status values are stored in English and labelled from ToolStatus::label(); the server sends `statusLabel` on the tool and `label` on every filter option, matching SubmissionSummary/ToolRunSummary/ToolRequest. Never add a second label map in TypeScript.

## The catalog saves its filter through useHttp, not a visit
tools.filters.save answers with 204 and no props: it is reached from `useHttp` (Inertia v3), so nothing re-renders and the catalog does not reload while someone is ticking boxes. Do not turn it back into a redirect - the screen never navigates here.
The selection lives in the request's own data (`saver.data.filters`), not a second useState, so a save always sends what is on screen. Saving is debounced by SAVE_DELAY_MS and skipped when the fingerprint is unchanged, and a second effect flushes on unmount - ticking a box then opening a tool is well inside the delay, and that tick must not be the one that is lost.
The lint rule react-hooks/refs forbids assigning to a ref during render; keep the flush closure fresh inside an effect with no dependency array.
A save that works says nothing on screen - the boxes already show what the catalog will open with - and only a failure gets a line. Report failures through useHttp's callbacks, never a promise chain alone: a 422 does not reject, it fills in `errors` and resolves, so `.then()` would call a rejected filter saved. onHttpException and onNetworkError report and then rethrow, so keep a `.catch()` on the end.
