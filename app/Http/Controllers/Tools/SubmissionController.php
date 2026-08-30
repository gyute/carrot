<?php

namespace App\Http\Controllers\Tools;

use App\Enums\SubmissionAction;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tools\ToolSubmissionRequest;
use App\Models\Tool;
use App\Models\ToolSubmission;
use App\Support\Presenters\SubmissionPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The requester's side of the approval flow: drafting, submitting and
 * withdrawing requests to register or change a tool.
 */
class SubmissionController extends Controller
{
    public function __construct(private SubmissionPresenter $presenter) {}

    /**
     * The visitor's own requests, newest first.
     */
    public function index(Request $request): Response
    {
        $submissions = ToolSubmission::query()
            ->with(['tool', 'reviewer'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(100)
            ->get();

        return Inertia::render('tools/submissions/index', [
            'submissions' => $submissions->map($this->presenter->summary(...))->all(),
        ]);
    }

    /**
     * The form for a new tool, or for a behaviour change on `tool`.
     */
    public function create(Request $request, ?Tool $tool = null): Response
    {
        if ($tool !== null) {
            Gate::authorize('submitChange', $tool);
        }

        return Inertia::render('tools/submissions/form', [
            'submission' => null,
            'tool' => $tool === null ? null : $this->presenter->toolSummary($tool),
            'initial' => $tool === null ? null : $this->presenter->payloadFromTool($tool),
            'limits' => $this->presenter->limits(),
        ]);
    }

    /**
     * Save a draft. Nothing reaches an admin until it is submitted.
     */
    public function store(ToolSubmissionRequest $request, ?Tool $tool = null): RedirectResponse
    {
        if ($tool !== null) {
            Gate::authorize('submitChange', $tool);
        }

        $submission = ToolSubmission::query()->create([
            'user_id' => $request->user()->id,
            'tool_id' => $tool?->id,
            'action' => $tool === null ? SubmissionAction::Create : SubmissionAction::Update,
            'status' => SubmissionStatus::Draft,
            'payload' => $tool === null ? $request->payload() : $request->behaviourPayload(),
            'note' => $request->validated('note'),
        ]);

        if ($request->boolean('submit')) {
            return $this->submit($request, $submission);
        }

        return to_route('tools.submissions.show', $submission)->with('status', '下書きを保存しました。');
    }

    /**
     * Ask to retire a tool. Needs no form: the request carries no payload.
     */
    public function deprecate(Request $request, Tool $tool): RedirectResponse
    {
        Gate::authorize('submitChange', $tool);

        abort_unless($tool->isRunning(), 422, 'すでに非推奨です。');

        $submission = ToolSubmission::query()->create([
            'user_id' => $request->user()->id,
            'tool_id' => $tool->id,
            'action' => SubmissionAction::Deprecate,
            'status' => SubmissionStatus::Pending,
            'payload' => [],
            'note' => $request->string('note')->limit(2000)->value() ?: null,
            'submitted_at' => now(),
        ]);

        return to_route('tools.submissions.show', $submission)->with('status', '非推奨化を申請しました。');
    }

    public function show(ToolSubmission $submission): Response
    {
        Gate::authorize('view', $submission);

        $submission->load(['tool.tags', 'user', 'reviewer']);

        return Inertia::render('tools/submissions/show', [
            'submission' => $this->presenter->detail($submission),
            'can' => [
                'update' => Gate::allows('update', $submission),
                'withdraw' => Gate::allows('withdraw', $submission),
            ],
        ]);
    }

    public function edit(ToolSubmission $submission): Response
    {
        Gate::authorize('update', $submission);

        $submission->load('tool.tags');

        return Inertia::render('tools/submissions/form', [
            'submission' => $this->presenter->summary($submission),
            'tool' => $submission->tool === null ? null : $this->presenter->toolSummary($submission->tool),
            'initial' => $submission->action === SubmissionAction::Update && $submission->tool !== null
                ? [...$this->presenter->payloadFromTool($submission->tool), ...$submission->payload]
                : $submission->payload,
            'limits' => $this->presenter->limits(),
        ]);
    }

    public function update(ToolSubmissionRequest $request, ToolSubmission $submission): RedirectResponse
    {
        Gate::authorize('update', $submission);

        $submission->fill([
            'payload' => $submission->action === SubmissionAction::Create
                ? $request->payload()
                : $request->behaviourPayload(),
            'note' => $request->validated('note'),
        ])->save();

        if ($request->boolean('submit')) {
            return $this->submit($request, $submission);
        }

        return to_route('tools.submissions.show', $submission)->with('status', '下書きを保存しました。');
    }

    /**
     * Draft → pending. From here it waits for a reviewer.
     */
    public function submit(Request $request, ToolSubmission $submission): RedirectResponse
    {
        Gate::authorize('submit', $submission);

        $submission->forceFill([
            'status' => SubmissionStatus::Pending,
            'submitted_at' => now(),
        ])->save();

        return to_route('tools.submissions.show', $submission)->with('status', '申請しました。承認をお待ちください。');
    }

    /**
     * Withdraw a draft or a pending request. A draft simply disappears.
     */
    public function destroy(ToolSubmission $submission): RedirectResponse
    {
        Gate::authorize('withdraw', $submission);

        if ($submission->status === SubmissionStatus::Draft) {
            $submission->delete();

            return to_route('tools.submissions.index')->with('status', '下書きを削除しました。');
        }

        $submission->forceFill(['status' => SubmissionStatus::Withdrawn])->save();

        return to_route('tools.submissions.show', $submission)->with('status', '申請を取り下げました。');
    }
}
