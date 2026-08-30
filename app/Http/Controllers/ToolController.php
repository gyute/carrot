<?php

namespace App\Http\Controllers;

use App\Actions\Tools\SyncToolTags;
use App\Enums\SubmissionAction;
use App\Enums\SubmissionStatus;
use App\Enums\ToolKind;
use App\Enums\ToolStatus;
use App\Http\Requests\Tools\ToolMetadataRequest;
use App\Models\Tool;
use App\Models\ToolSubmission;
use App\Models\User;
use App\Support\Presenters\SubmissionPresenter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

    public function __construct(private SubmissionPresenter $presenter) {}

    /**
     * Show the catalog of in-house tools: every published tool, plus the
     * visitor's own (or, for an admin, everyone's) requests for new tools
     * that are still waiting for approval.
     */
    public function index(Request $request): Response
    {
        $tools = Tool::query()
            ->with('tags')
            ->orderBy('name')
            ->get()
            ->map($this->present(...));

        $pending = $this->pendingCreates($request->user())->map($this->presentPending(...));

        $entries = $tools->concat($pending)->sortBy('name', SORT_NATURAL)->values();

        return Inertia::render('tools/index', [
            'tools' => $entries->all(),
            'tagGroups' => $this->tagGroups($entries->all()),
        ]);
    }

    /**
     * The tool's own page: what it is, and the screen it is used from when it
     * is not just a link out.
     */
    public function show(Request $request, Tool $tool): Response
    {
        Gate::authorize('view', $tool);

        $tool->load(['tags', 'owner', 'requester', 'endorser', 'approver']);

        $history = $tool->submissions()
            ->with(['user', 'reviewer', 'endorser'])
            ->where('status', SubmissionStatus::Approved)
            ->latest('reviewed_at')
            ->get();

        $openChange = $tool->submissions()
            ->with(['user', 'reviewer', 'endorser'])
            ->where('user_id', $request->user()->id)
            ->whereIn('status', [SubmissionStatus::Draft, SubmissionStatus::Pending, SubmissionStatus::Endorsed])
            ->latest()
            ->first();

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
                'endorser' => $tool->endorser?->name,
                'approver' => $tool->approver?->name,
                'publishedAt' => $tool->published_at?->toIso8601String(),
                'deprecatedAt' => $tool->deprecated_at?->toIso8601String(),
                'pendingChange' => $tool->submissions()->pending()->exists(),
            ],
            'history' => $history->map($this->presenter->summary(...))->all(),
            'openChange' => $openChange === null ? null : $this->presenter->summary($openChange),
            'limits' => $this->presenter->limits(),
            'can' => [
                'updateMetadata' => Gate::allows('updateMetadata', $tool),
                'submitChange' => Gate::allows('submitChange', $tool),
                'manage' => Gate::allows('manage', $tool),
                'delete' => Gate::allows('delete', $tool),
            ],
        ]);
    }

    /**
     * Edit the display fields in place. No review: nothing here changes what
     * the tool does.
     */
    public function update(ToolMetadataRequest $request, Tool $tool, SyncToolTags $syncTags): RedirectResponse
    {
        Gate::authorize('updateMetadata', $tool);

        $tool->fill($request->safe()->except('categories'))->save();
        $syncTags->handle($tool, $request->validated('categories', []));

        return to_route('tools.show', $tool)->with('status', '表示内容を更新しました。');
    }

    /**
     * @return Collection<int, ToolSubmission>
     */
    private function pendingCreates(User $user): Collection
    {
        return ToolSubmission::query()
            ->pending()
            ->where('action', SubmissionAction::Create)
            ->when(! $user->isAdmin(), fn ($query) => $query->where('user_id', $user->id))
            ->latest('submitted_at')
            ->get();
    }

    /**
     * A requested tool shown in the catalog as `pending`. Its card leads to
     * the request itself.
     *
     * @return array{ulid: string, slug: string, kind: string, name: string, summary: string, icon: string, accent: string, status: string, href: string|null, tags: array<string, array<int, string>>}
     */
    private function presentPending(ToolSubmission $submission): array
    {
        $payload = $submission->payload;

        return [
            'ulid' => $submission->ulid,
            'slug' => '',
            'kind' => (string) ($payload['kind'] ?? 'link'),
            'name' => $submission->displayName(),
            'summary' => (string) ($payload['summary'] ?? ''),
            'icon' => (string) ($payload['icon'] ?? 'wrench'),
            'accent' => (string) ($payload['accent'] ?? 'slate'),
            'status' => 'pending',
            'href' => route('tools.submissions.show', $submission, absolute: false),
            'tags' => [
                'status' => ['pending'],
                'category' => array_values(array_filter((array) ($payload['categories'] ?? []), 'is_string')),
                'department' => isset($payload['department']) && is_string($payload['department']) ? [$payload['department']] : [],
            ],
        ];
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
     * then pending, then deprecated.
     *
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function orderStatuses(array $counts): array
    {
        $ordered = [];

        foreach ([ToolStatus::Running->value, 'pending', ToolStatus::Deprecated->value] as $status) {
            if (isset($counts[$status])) {
                $ordered[$status] = $counts[$status];
            }
        }

        return $ordered;
    }
}
