---
paths:
  - 'app/Http/Controllers/Tools/**'
---

# Tools

## Only behaviour changes need approval; display fields are edited in place
Tool (published snapshot) and ToolSubmission (request) are separate. A create submission carries the full payload; an update submission carries only config + source (SubmissionController uses ToolSubmissionRequest::behaviourPayload()). Name/summary/description/icon/accent/department/categories go through ToolController@update (policy updateMetadata) with no review. slug and kind never change after approval.
Routes: static `tools/submissions*` paths are registered before `tools/{tool}` on purpose, so "submissions" is never read as a tool ULID.
Departments are deployment data, not code: CATALOG_DEPARTMENTS feeds config/catalog.php, and App\Support\Departments::rules() falls back to free text when the list is empty. Never commit a real org chart.
