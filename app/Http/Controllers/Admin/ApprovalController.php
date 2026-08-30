<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Tools\ApproveSubmission;
use App\Actions\Tools\EndorseSubmission;
use App\Actions\Tools\RejectSubmission;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewSubmissionRequest;
use App\Models\ToolSubmission;
use App\Support\Presenters\SubmissionPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function __construct(private SubmissionPresenter $presenter) {}

    /**
     * What this reviewer has to act on first, then what has been decided.
     * A manager sees their department's first-stage requests; an admin sees
     * every open request.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $base = ToolSubmission::query()->with(['tool', 'user', 'reviewer', 'endorser']);

        $pending = (clone $base)->awaitingReviewBy($user)->oldest('submitted_at')->get();

        $decided = (clone $base)
            ->whereIn('status', [SubmissionStatus::Approved, SubmissionStatus::Rejected])
            ->when($user->isManager(), fn ($query) => $query->where('endorsed_by', $user->id))
            ->latest('reviewed_at')
            ->limit(100)
            ->get();

        return Inertia::render('admin/approvals/index', [
            'pending' => $pending->map($this->presenter->summary(...))->all(),
            'decided' => $decided->map($this->presenter->summary(...))->all(),
            'stage' => $user->isAdmin() ? 'admin' : 'manager',
        ]);
    }

    public function show(Request $request, ToolSubmission $submission): Response
    {
        Gate::authorize('view', $submission);

        $submission->load(['tool.tags', 'user', 'reviewer', 'endorser']);

        return Inertia::render('admin/approvals/show', [
            'submission' => $this->presenter->detail($submission),
            'can' => [
                'review' => Gate::allows('review', $submission),
                'finalize' => Gate::allows('finalize', $submission),
            ],
        ]);
    }

    /**
     * A manager's approval endorses the request on to the admins; an admin's
     * publishes it.
     */
    public function approve(ReviewSubmissionRequest $request, ToolSubmission $submission, ApproveSubmission $approve, EndorseSubmission $endorse): RedirectResponse
    {
        Gate::authorize('review', $submission);

        if (! Gate::allows('finalize', $submission)) {
            $endorse->handle($submission, $request->user(), $request->validated('comment'));

            return to_route('admin.approvals.show', $submission)
                ->with('status', '部署として承認しました。システム管理者の確認に進みます。');
        }

        $tool = $approve->handle($submission, $request->user(), $request->validated('comment'));

        return to_route('admin.approvals.show', $submission)
            ->with('status', "承認しました。{$tool->name} は v{$tool->version} になりました。");
    }

    public function reject(ReviewSubmissionRequest $request, ToolSubmission $submission, RejectSubmission $reject): RedirectResponse
    {
        Gate::authorize('review', $submission);

        $reject->handle($submission, $request->user(), (string) $request->validated('comment'));

        return to_route('admin.approvals.show', $submission)->with('status', '差し戻しました。');
    }
}
