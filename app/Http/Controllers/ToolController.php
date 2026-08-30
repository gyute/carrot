<?php

namespace App\Http\Controllers;

use App\Enums\ToolKind;
use App\Enums\ToolStatus;
use App\Models\Tool;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ToolController extends Controller
{
    /**
     * Filter groups shown by the tag filter, in display order. Status comes
     * off the tool's own column, department off the tool as well, category
     * off the tags table.
     */
    private const TAG_GROUPS = [
        'status' => 'ステータス',
        'category' => 'カテゴリ',
        'department' => '所属',
    ];

    /**
     * Show the catalog of in-house tools: every published tool, whatever its
     * status. Deprecated ones are hidden by the screen until asked for.
     */
    public function index(): Response
    {
        $tools = Tool::query()
            ->with('tags')
            ->orderBy('name')
            ->get()
            ->map($this->present(...))
            ->values();

        return Inertia::render('tools/index', [
            'tools' => $tools->all(),
            'tagGroups' => $this->tagGroups($tools->all()),
        ]);
    }

    /**
     * The tool's own page: what it is, and the screen it is used from when it
     * is not just a link out.
     */
    public function show(Request $request, Tool $tool): Response
    {
        $tool->load(['tags', 'owner', 'requester', 'approver']);

        return Inertia::render('tools/show', [
            'tool' => [
                ...$this->present($tool),
                'description' => $tool->description,
                'department' => $tool->department,
                'categories' => $tool->categories(),
                'config' => $tool->config,
                'embedUrl' => $tool->kind === ToolKind::Embed ? $tool->frameableUrl() : null,
                'version' => $tool->version,
                'owner' => $tool->owner?->name,
                'requester' => $tool->requester?->name,
                'approver' => $tool->approver?->name,
                'publishedAt' => $tool->published_at?->toIso8601String(),
                'deprecatedAt' => $tool->deprecated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * @return array{ulid: string, slug: string, kind: string, name: string, summary: string, icon: string, accent: string, status: string, href: string|null, tags: array<string, array<int, string>>}
     */
    private function present(Tool $tool): array
    {
        return [
            'ulid' => $tool->ulid,
            'slug' => $tool->slug,
            'kind' => $tool->kind->value,
            'name' => $tool->name,
            'summary' => $tool->summary,
            'icon' => $tool->icon,
            'accent' => $tool->accent,
            'status' => $tool->status->value,
            'href' => $this->href($tool),
            'tags' => $this->tagsOf($tool),
        ];
    }

    /**
     * Where the card leads. A deprecated tool leads to its own page, which
     * says so; an embed tool is framed on its own page.
     */
    private function href(Tool $tool): ?string
    {
        if (! $tool->isRunning()) {
            return route('tools.show', $tool, absolute: false);
        }

        return match ($tool->kind) {
            ToolKind::Link => $tool->url(),
            ToolKind::Embed, ToolKind::Script => route('tools.show', $tool, absolute: false),
        };
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function tagsOf(Tool $tool): array
    {
        return [
            'status' => [$tool->status->value],
            'category' => $tool->categories(),
            'department' => $tool->department === null ? [] : [$tool->department],
        ];
    }

    /**
     * Distinct values per group with counts, in the shape the tag filter
     * component expects. Groups with no values are dropped.
     *
     * @param  array<int, array{tags: array<string, array<int, string>>}>  $entries
     * @return array<int, array{key: string, label: string, options: array<int, array{value: string, count: int}>}>
     */
    private function tagGroups(array $entries): array
    {
        $groups = [];

        foreach (self::TAG_GROUPS as $key => $label) {
            $counts = [];

            foreach ($entries as $entry) {
                foreach ($entry['tags'][$key] ?? [] as $value) {
                    $counts[$value] = ($counts[$value] ?? 0) + 1;
                }
            }

            if ($key === 'status') {
                $counts = $this->orderStatuses($counts);
            } else {
                ksort($counts, SORT_NATURAL);
            }

            if ($counts === []) {
                continue;
            }

            $groups[] = [
                'key' => $key,
                'label' => $label,
                'options' => collect($counts)
                    ->map(fn (int $count, string $value): array => ['value' => $value, 'count' => $count])
                    ->values()
                    ->all(),
            ];
        }

        return $groups;
    }

    /**
     * Status values follow a fixed order rather than the alphabet: running,
     * then deprecated.
     *
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function orderStatuses(array $counts): array
    {
        $ordered = [];

        foreach ([ToolStatus::Running->value, ToolStatus::Deprecated->value] as $status) {
            if (isset($counts[$status])) {
                $ordered[$status] = $counts[$status];
            }
        }

        return $ordered;
    }
}
