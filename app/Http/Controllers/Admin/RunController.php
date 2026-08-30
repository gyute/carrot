<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Tools\PruneToolRuns;
use App\Enums\ToolRunStatus;
use App\Http\Controllers\Controller;
use App\Models\ToolRun;
use App\Support\Presenters\ToolRunPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every sandbox run, whoever asked for it. The tool's own page only shows a
 * user their own; this is the operational view, and the one place a run can
 * be deleted without waiting for the retention window.
 */
class RunController extends Controller
{
    private const PER_PAGE = 30;

    public function __construct(private ToolRunPresenter $presenter) {}

    public function index(Request $request): Response
    {
        $status = ToolRunStatus::tryFrom((string) $request->query('status', ''));

        $runs = ToolRun::query()
            ->with(['user', 'tool', 'submission'])
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/runs/index', [
            'runs' => [
                'data' => collect($runs->items())->map(fn (ToolRun $run): array => [
                    ...$this->presenter->present($run),
                    'tool' => $run->tool === null ? null : ['ulid' => $run->tool->ulid, 'name' => $run->tool->name],
                    'submission' => $run->submission === null ? null : ['ulid' => $run->submission->ulid],
                ])->all(),
                'currentPage' => $runs->currentPage(),
                'lastPage' => $runs->lastPage(),
                'total' => $runs->total(),
            ],
            'filters' => ['status' => $status?->value],
            'statuses' => collect(ToolRunStatus::cases())
                ->map(fn (ToolRunStatus $case): array => ['value' => $case->value, 'label' => $case->label()])
                ->all(),
            'retentionDays' => (int) config('sandbox.run_retention_days'),
        ]);
    }

    public function destroy(ToolRun $run): RedirectResponse
    {
        $run->delete();

        return back()->with('status', '実行履歴を削除しました。');
    }

    /**
     * The same sweep the daily schedule runs, on demand.
     */
    public function prune(PruneToolRuns $prune): RedirectResponse
    {
        $result = $prune->handle();

        return back()->with('status', "{$result['days']} 日より古い実行 {$result['runs']} 件と作業ディレクトリ {$result['workdirs']} 件を削除しました。");
    }
}
