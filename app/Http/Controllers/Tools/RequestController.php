<?php

namespace App\Http\Controllers\Tools;

use App\Enums\ToolRequestStatus;
use App\Events\ToolRequestSubmitted;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tools\SubmitToolRequest;
use App\Models\ToolRequest;
use App\Support\Presenters\ToolRequestPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The requester's side: asking the development team for a tool that does not
 * exist yet, and following what happened to the request.
 */
class RequestController extends Controller
{
    public function __construct(private ToolRequestPresenter $presenter) {}

    /**
     * Everything the visitor may see: their own requests and their
     * department's, newest first.
     */
    public function index(Request $request): Response
    {
        $requests = ToolRequest::query()
            ->with(['user', 'tool', 'decider', 'duplicateOf'])
            ->visibleTo($request->user())
            ->latest()
            ->limit(100)
            ->get();

        return Inertia::render('tools/requests/index', [
            'requests' => $requests->map($this->presenter->summary(...))->all(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', ToolRequest::class);

        return Inertia::render('tools/requests/form', [
            'toolRequest' => null,
            'limits' => $this->presenter->limits($request->user()->department),
        ]);
    }

    /**
     * A request needs no draft stage: it is a title and a paragraph, and it
     * reaches the development team as soon as it is written.
     */
    public function store(SubmitToolRequest $request): RedirectResponse
    {
        Gate::authorize('create', ToolRequest::class);

        $toolRequest = ToolRequest::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            // Not the requester's to choose: it decides who else may read this.
            'department' => $request->user()->department,
            'status' => ToolRequestStatus::Open,
            'categories' => array_values($request->validated('categories', [])),
        ]);

        ToolRequestSubmitted::dispatch($toolRequest);

        return to_route('tools.requests.show', $toolRequest)
            ->with('status', '依頼を送りました。開発チームからの連絡をお待ちください。');
    }

    public function show(ToolRequest $toolRequest): Response
    {
        Gate::authorize('view', $toolRequest);

        $toolRequest->load(['user', 'tool', 'assignee', 'decider', 'duplicateOf']);

        return Inertia::render('tools/requests/show', [
            'toolRequest' => $this->presenter->detail($toolRequest),
            'can' => [
                'update' => Gate::allows('update', $toolRequest),
                'withdraw' => Gate::allows('withdraw', $toolRequest),
                'triage' => Gate::allows('triage', $toolRequest),
            ],
        ]);
    }

    public function edit(Request $request, ToolRequest $toolRequest): Response
    {
        Gate::authorize('update', $toolRequest);

        return Inertia::render('tools/requests/form', [
            'toolRequest' => $this->presenter->detail($toolRequest),
            'limits' => $this->presenter->limits($toolRequest->department),
        ]);
    }

    public function update(SubmitToolRequest $request, ToolRequest $toolRequest): RedirectResponse
    {
        Gate::authorize('update', $toolRequest);

        $toolRequest->fill([
            ...$request->validated(),
            'categories' => array_values($request->validated('categories', [])),
        ])->save();

        return to_route('tools.requests.show', $toolRequest)->with('status', '依頼を更新しました。');
    }

    public function destroy(ToolRequest $toolRequest): RedirectResponse
    {
        Gate::authorize('withdraw', $toolRequest);

        $toolRequest->forceFill(['status' => ToolRequestStatus::Withdrawn])->save();

        return to_route('tools.requests.show', $toolRequest)->with('status', '依頼を取り下げました。');
    }
}
