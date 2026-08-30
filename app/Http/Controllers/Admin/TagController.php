<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Category tags are created by whatever a requester typed, so they drift:
 * two spellings of one word, a typo nobody can fix from the tool page. This
 * is where they are renamed, merged and dropped.
 */
class TagController extends Controller
{
    public function index(): Response
    {
        $tags = Tag::query()
            ->withCount('tools')
            ->orderBy('group')
            ->orderBy('value')
            ->get();

        return Inertia::render('admin/tags/index', [
            'tags' => $tags->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'group' => $tag->group,
                'value' => $tag->value,
                'tools' => $tag->tools_count,
            ])->all(),
        ]);
    }

    /**
     * Renaming onto a name that already exists merges the two: every tool on
     * the old tag moves to the surviving one and the old row goes.
     */
    public function update(UpdateTagRequest $request, Tag $tag): RedirectResponse
    {
        $value = (string) $request->validated('value');

        $existing = Tag::query()
            ->where('group', $tag->group)
            ->where('value', $value)
            ->whereKeyNot($tag->id)
            ->first();

        if ($existing === null) {
            $tag->forceFill(['value' => $value])->save();

            return back()->with('status', "「{$value}」に変更しました。");
        }

        DB::transaction(function () use ($tag, $existing): void {
            $existing->tools()->syncWithoutDetaching($tag->tools()->pluck('tools.id'));
            $tag->tools()->detach();
            $tag->delete();
        });

        return back()->with('status', "「{$value}」に統合しました。");
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $value = $tag->value;

        DB::transaction(function () use ($tag): void {
            $tag->tools()->detach();
            $tag->delete();
        });

        return back()->with('status', "「{$value}」を削除しました。");
    }
}
