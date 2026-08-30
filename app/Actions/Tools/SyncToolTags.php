<?php

namespace App\Actions\Tools;

use App\Models\Tag;
use App\Models\Tool;

class SyncToolTags
{
    /**
     * Replace the tool's category tags with the given values, creating any
     * that do not exist yet.
     *
     * @param  array<int, string>  $categories
     */
    public function handle(Tool $tool, array $categories): void
    {
        $ids = collect($categories)
            ->map(fn (string $value): string => trim($value))
            ->filter()
            ->unique()
            ->map(fn (string $value): int => Tag::query()->firstOrCreate([
                'group' => Tag::GROUP_CATEGORY,
                'value' => $value,
            ])->id);

        $tool->tags()->sync($ids);
    }
}
