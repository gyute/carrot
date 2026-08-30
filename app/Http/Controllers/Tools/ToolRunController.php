<?php

namespace App\Http\Controllers\Tools;

use App\Actions\Tools\StartToolRun;
use App\Http\Controllers\Controller;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Support\Presenters\ToolRunPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ToolRunController extends Controller
{
    public function __construct(private ToolRunPresenter $presenter) {}

    public function store(Request $request, Tool $tool, StartToolRun $start): RedirectResponse
    {
        Gate::authorize('run', $tool);

        $inputs = $request->input('inputs', []);

        $run = $start->forTool($tool, $request->user(), is_array($inputs) ? $inputs : []);

        return to_route('tools.runs.show', [$tool, $run]);
    }

    public function show(Request $request, Tool $tool, ToolRun $run): Response
    {
        Gate::authorize('view', $tool);

        abort_unless($run->tool_id === $tool->id, 404);
        abort_unless($run->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        $run->load('user');

        return Inertia::render('tools/runs/show', [
            'tool' => [
                'ulid' => $tool->ulid,
                'name' => $tool->name,
                'icon' => $tool->icon,
                'accent' => $tool->accent,
            ],
            'run' => $this->presenter->present($run),
        ]);
    }
}
