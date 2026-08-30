<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Tools\DeliverToolRequest;
use App\Actions\Tools\TriageToolRequest;
use App\Enums\ToolRequestPriority;
use App\Enums\ToolRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewToolRequest;
use App\Models\Tool;
use App\Models\ToolRequest;
use App\Models\User;
use App\Support\Features;
use App\Support\Presenters\ToolRequestPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The development team's queue. Unlike approvals there is no second stage:
 * one team decides, and delivering a tool is what closes a request.
 */
class ToolRequestController extends Controller
{
    public function __construct(private ToolRequestPresenter $presenter) {}

    public function index(): Response
    {
        $base = ToolRequest::query()->with(['user', 'tool', 'assignee', 'decider', 'duplicateOf']);

        $open = (clone $base)->awaitingTriage()->oldest()->get();
        $working = (clone $base)
            ->whereIn('status', [ToolRequestStatus::Accepted, ToolRequestStatus::InProgress])
            ->oldest('decided_at')
            ->get();
        $closed = (clone $base)
            ->whereNotIn('status', ToolRequestStatus::live())
            ->latest('decided_at')
            ->limit(100)
            ->get();

        return Inertia::render('admin/requests/index', [
            'open' => $open->map($this->presenter->summary(...))->all(),
            'working' => $working->map($this->presenter->summary(...))->all(),
            'closed' => $closed->map($this->presenter->summary(...))->all(),
        ]);
    }

    public function show(ToolRequest $toolRequest): Response
    {
        Gate::authorize('view', $toolRequest);

        $toolRequest->load(['user', 'tool', 'assignee', 'decider', 'duplicateOf']);

        return Inertia::render('admin/requests/show', [
            'toolRequest' => $this->presenter->detail($toolRequest),
            'can' => [
                'triage' => Gate::allows('triage', $toolRequest),
                // Delivering means registering the tool, so it needs a
                // deployment that still lets anyone register one.
                'deliver' => Gate::allows('triage', $toolRequest) && Features::submissions(),
            ],
            'candidates' => $this->candidates($toolRequest),
            'assignees' => $this->assignees(),
        ]);
    }

    public function accept(ReviewToolRequest $request, ToolRequest $toolRequest, TriageToolRequest $triage): RedirectResponse
    {
        Gate::authorize('triage', $toolRequest);

        $triage->accept(
            $toolRequest,
            $request->user(),
            $request->validated('comment'),
            ToolRequestPriority::tryFrom((string) $request->validated('priority')),
            $this->assignee($request),
        );

        return to_route('admin.requests.show', $toolRequest)->with('status', '対応予定にしました。');
    }

    public function start(ReviewToolRequest $request, ToolRequest $toolRequest, TriageToolRequest $triage): RedirectResponse
    {
        Gate::authorize('triage', $toolRequest);

        $triage->start($toolRequest, $request->user(), $request->validated('comment'));

        return to_route('admin.requests.show', $toolRequest)->with('status', '対応中にしました。');
    }

    public function decline(ReviewToolRequest $request, ToolRequest $toolRequest, TriageToolRequest $triage): RedirectResponse
    {
        Gate::authorize('triage', $toolRequest);

        $triage->decline($toolRequest, $request->user(), (string) $request->validated('comment'));

        return to_route('admin.requests.show', $toolRequest)->with('status', '見送りにしました。');
    }

    public function duplicate(ReviewToolRequest $request, ToolRequest $toolRequest, TriageToolRequest $triage): RedirectResponse
    {
        Gate::authorize('triage', $toolRequest);

        $original = ToolRequest::query()->where('ulid', $request->validated('duplicate_of'))->sole();

        $triage->duplicate($toolRequest, $request->user(), $original, $request->validated('comment'));

        return to_route('admin.requests.show', $toolRequest)->with('status', '重複としてまとめました。');
    }

    public function deliver(ReviewToolRequest $request, ToolRequest $toolRequest, DeliverToolRequest $deliver): RedirectResponse
    {
        Gate::authorize('triage', $toolRequest);

        $tool = Tool::query()->where('ulid', $request->validated('tool'))->sole();

        $deliver->handle($toolRequest, $tool, $request->user());

        return to_route('admin.requests.show', $toolRequest)->with('status', '公開済みにしました。');
    }

    private function assignee(Request $request): ?User
    {
        $ulid = $request->input('assignee');

        return is_string($ulid) && $ulid !== ''
            ? User::query()->developmentTeam()->where('ulid', $ulid)->first()
            : null;
    }

    /**
     * Who the request may be assigned to, as a list rather than a text field
     * so there is no handle to mistype.
     *
     * @return array<int, array{ulid: string, name: string}>
     */
    private function assignees(): array
    {
        return User::query()
            ->developmentTeam()
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => ['ulid' => $user->ulid, 'name' => $user->name])
            ->all();
    }

    /**
     * Tools the team may point this request at. Kept small and recent - the
     * answer is nearly always something published just now.
     *
     * @return array<int, array{ulid: string, name: string}>
     */
    private function candidates(ToolRequest $toolRequest): array
    {
        if ($toolRequest->status === ToolRequestStatus::Delivered) {
            return [];
        }

        return Tool::query()
            ->running()
            ->latest('published_at')
            ->limit(50)
            ->get()
            ->map(fn (Tool $tool): array => ['ulid' => $tool->ulid, 'name' => $tool->name])
            ->all();
    }
}
