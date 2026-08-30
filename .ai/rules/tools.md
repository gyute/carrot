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

## Two words for two things: a 依頼 asks, a 申請 brings
The tool module has two flows that both sound like "requesting", so the wording is fixed. Use it in UI labels, docs and comments; do not invent synonyms ("ask", "リクエスト", "리퀘스트" and "registration" were each used once and cost a rename).

| Table | English | Japanese | Korean | What the person brings |
| --- | --- | --- | --- | --- |
| tool_requests | Request | 依頼 | 의뢰 | a problem; the development team builds it |
| tool_submissions | Submission | 登録 | 등록 | a finished tool; reviewers approve it |

依頼 → the team builds → 申請 → approval publishes the tool, and that approval closes the 依頼 (ToolSubmission::tool_request_id). They chain; they are not alternatives.

The English and the Japanese deliberately disagree, and that is not a mistake to tidy up. 依頼 and 申請 are near synonyms - both are 〜請 - so side by side in a tab strip nobody can pick, which is why the section is labelled 登録. Request and Submission have no such collision, and "submission" is what the row actually is: Merriam-Webster gives "an act of submitting something (as for consideration or inspection); also: something submitted", and App Store Connect names the same object the same way. So English matches the class names and needs no translation layer; only the Japanese and Korean labels move.

`申請` stays the *act* inside the 登録 section - 申請者, 申請日時, 申請メモ, 申請する, flash and notification copy. Only the section name (tab, page title, back links) is 登録, because that is the one place it sat beside 依頼. Renaming every 申請 would be wrong: the section also covers 変更 and 非推奨化, which are not registrations.

## The mirror follows the row, not the code path that changed it
`MirrorToolToRepo` is dispatched from four model events on `Tool` (saved, deleted, restored, forceDeleted), registered once in `AppServiceProvider::mirrorToolChanges()`. It takes a ULID and re-reads the row; it is never handed a diff.

That is deliberate. Eight code paths write to `tools` - ApproveSubmission, ToolController@update, the five admin actions, and RetireUser handing tools to a successor - and the count has already grown once after a design that hooked them one at a time. Re-reading also settles ordering: ToolController@update syncs tags *after* saving the row, and a queued job sees both.

Three things that follow from it:
- A query-builder `update()` writes rows without raising a model event. `RetireUser` saves each tool one at a time for exactly this reason - do not "optimise" it back into a bulk update.
- `ShouldBeUniqueUntilProcessing`, not `ShouldBeUnique`: the lock has to release when the write starts, or a change made mid-write is dropped and the mirror stops at a stale state.
- A purged tool has no row left, so the job carries the slug as well as the ULID and removes the directory when the row is gone.

Nothing personal is committed. `ToolDocument` writes ULIDs for owner/requester/endorser/approver and leaves the department out. Git only adds: a name committed once cannot be taken back, which would undo retiring a person rather than deleting them (`.ai/rules/app.md`).

GitHub is never in the way of an approval. The job is queued outside the transaction, retries with backoff, and a failure shows up as a failed job on `/admin/system` - `GitHub::check()` there also refuses a public repository, since mirroring internal tooling to one would publish it.

