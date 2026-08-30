<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ToolStatus;
use App\Http\Controllers\Controller;
use App\Models\Tool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Immediate changes an admin makes without a submission.
 */
class ToolController extends Controller
{
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
}
