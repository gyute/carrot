---
paths:
  - 'app/Http/Controllers/Tools/**'
---

# Tools

## Only behaviour changes need approval; display fields are edited in place
Tool (published snapshot) and ToolSubmission (request) are separate. A create submission carries the full payload; an update submission carries only config + source (SubmissionController uses ToolSubmissionRequest::behaviourPayload()). Name/summary/description/icon/accent/department/categories go through ToolController@update (policy updateMetadata) with no review. slug and kind never change after approval.
Routes: static `tools/submissions*` paths are registered before `tools/{tool}` on purpose, so "submissions" is never read as a tool ULID.
Departments are deployment data, not code: CATALOG_DEPARTMENTS feeds config/catalog.php, and App\Support\Departments::rules() falls back to free text when the list is empty. Never commit a real org chart.
ApproveSubmission is the single place a tool is created/changed from a request: it stamps version (YmdHi, then YmdHi.2 within the same minute), requested_by/endorsed_by/approved_by and approved_submission_id. Approval is two-stage: ToolSubmissionPolicy::review lets the department manager (User::isManagerOf(submission->department())) or an admin act on `pending`; only an admin acts on `endorsed` (policy `finalize`). ApprovalController@approve endorses for a manager and publishes for an admin, so an admin acting at the first stage stands in for the department too.
