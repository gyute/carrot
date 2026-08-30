<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ToolStatus;
use App\Http\Controllers\Controller;
use App\Models\Tool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Immediate changes an admin makes without a submission.
 */
class ToolController extends Controller
{
    /**
     * Every tool the table holds, deleted ones included - the catalog only
     * ever shows what is published, so this is the one place a soft-deleted
     * row can be seen and brought back.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $state = (string) $request->query('state', '');

        $tools = Tool::withTrashed()
            ->with(['tags', 'owner'])
            ->when($search !== '', fn ($query) => $query->where(fn ($where) => $where
                ->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")
                ->orWhere('department', 'like', "%{$search}%")))
            ->when($state === 'deleted', fn ($query) => $query->whereNotNull('deleted_at'))
            ->when($state === ToolStatus::Running->value, fn ($query) => $query->whereNull('deleted_at')->where('status', ToolStatus::Running))
            ->when($state === ToolStatus::Deprecated->value, fn ($query) => $query->whereNull('deleted_at')->where('status', ToolStatus::Deprecated))
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/tools/index', [
            'tools' => $tools->map($this->present(...))->all(),
            'filters' => ['q' => $search, 'state' => $state],
            'counts' => [
                'running' => Tool::query()->where('status', ToolStatus::Running)->count(),
                'deprecated' => Tool::query()->where('status', ToolStatus::Deprecated)->count(),
                'deleted' => Tool::onlyTrashed()->count(),
            ],
        ]);
    }

    public function deprecate(Tool $tool): RedirectResponse
    {
        Gate::authorize('manage', $tool);

        $tool->forceFill([
            'status' => ToolStatus::Deprecated,
            'deprecated_at' => now(),
        ])->save();

        return back()->with('status', "{$tool->name} を非推奨にしました。");
    }

    public function restore(Tool $tool): RedirectResponse
    {
        Gate::authorize('manage', $tool);

        $tool->forceFill([
            'status' => ToolStatus::Running,
            'deprecated_at' => null,
        ])->save();

        return back()->with('status', "{$tool->name} を再び稼働中にしました。");
    }

    public function destroy(Tool $tool): RedirectResponse
    {
        Gate::authorize('delete', $tool);

        $tool->delete();

        return to_route('tools.index')->with('status', "{$tool->name} を削除しました。");
    }

    /**
     * Undo a delete. The row was only soft-deleted, so the catalog gets the
     * tool back exactly as it was, version and history included.
     */
    public function untrash(string $ulid): RedirectResponse
    {
        $tool = Tool::onlyTrashed()->where('ulid', $ulid)->firstOrFail();

        Gate::authorize('delete', $tool);

        $tool->restore();

        return back()->with('status', "{$tool->name} を元に戻しました。");
    }

    /**
     * Really gone: the row, its tag links, its submissions and its runs.
     */
    public function purge(string $ulid): RedirectResponse
    {
        $tool = Tool::onlyTrashed()->where('ulid', $ulid)->firstOrFail();

        Gate::authorize('delete', $tool);

        $name = $tool->name;
        $tool->forceDelete();

        return back()->with('status', "{$name} を完全に削除しました。");
    }

    /**
     * @return array{ulid: string, slug: string, name: string, icon: string, kind: string, kindLabel: string, status: string, department: string|null, owner: string|null, version: string|null, categories: array<int, string>, publishedAt: string|null, deprecatedAt: string|null, deletedAt: string|null}
     */
    private function present(Tool $tool): array
    {
        return [
            'ulid' => $tool->ulid,
            'slug' => $tool->slug,
            'name' => $tool->name,
            'icon' => $tool->icon,
            'kind' => $tool->kind->value,
            'kindLabel' => $tool->kind->label(),
            'status' => $tool->trashed() ? 'deleted' : $tool->status->value,
            'department' => $tool->department,
            'owner' => $tool->owner?->name,
            'version' => $tool->version,
            'categories' => $tool->categories(),
            'publishedAt' => $tool->published_at?->toIso8601String(),
            'deprecatedAt' => $tool->deprecated_at?->toIso8601String(),
            'deletedAt' => $tool->deleted_at?->toIso8601String(),
        ];
    }
}
