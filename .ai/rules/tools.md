---
paths:
  - 'app/Http/Controllers/Tools/**'
---

# Tools

## Only behaviour changes need approval; display fields are edited in place
Tool (published snapshot) and ToolSubmission (request) are separate. A create submission carries the full payload; an update submission carries only config + source (SubmissionController uses ToolSubmissionRequest::behaviourPayload()). Name/summary/description/icon/accent/department/categories go through ToolController@update (policy updateMetadata) with no review. slug and kind never change after approval.
Routes: static `tools/submissions*` paths are registered before `tools/{tool}` on purpose, so "submissions" is never read as a tool ULID.
Departments are deployment data, not code: CATALOG_DEPARTMENTS feeds config/catalog.php, and App\Support\Departments::rules() falls back to free text when the list is empty. Never commit a real org chart.
A newly published tool takes the ULID of the submission that created it, so `/tools/{ulid}` and the submission that produced it share one identifier; slugs would not do, since `Str::slug` drops Japanese and every tool here would be `tool`, `tool-2`.
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
