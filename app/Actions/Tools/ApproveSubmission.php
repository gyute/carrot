<?php

namespace App\Actions\Tools;

use App\Enums\SubmissionAction;
use App\Enums\SubmissionStatus;
use App\Enums\ToolStatus;
use App\Models\Tool;
use App\Models\ToolSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Copies an approved submission onto its tool. The tool is created for a
 * `create`, has its behaviour replaced for an `update`, and is retired for a
 * `deprecate`. Every approval stamps a new version on the tool.
 */
class ApproveSubmission
{
    public function __construct(private SyncToolTags $syncTags) {}

    public function handle(ToolSubmission $submission, User $reviewer, ?string $comment = null): Tool
    {
        $tool = DB::transaction(function () use ($submission, $reviewer, $comment): Tool {
            $tool = match ($submission->action) {
                SubmissionAction::Create => $this->create($submission),
                SubmissionAction::Update => $this->update($submission),
                SubmissionAction::Deprecate => $this->deprecate($submission),
            };

            // An admin approving straight from the first stage stands in for
            // the department as well.
            if ($submission->endorsed_by === null) {
                $submission->forceFill(['endorsed_by' => $reviewer->id, 'endorsed_at' => now()]);
            }

            $tool->forceFill([
                'version' => $this->nextVersion($tool),
                'requested_by' => $submission->user_id,
                'endorsed_by' => $submission->endorsed_by,
                'approved_by' => $reviewer->id,
                'approved_submission_id' => $submission->id,
            ])->save();

            $submission->forceFill([
                'tool_id' => $tool->id,
                'status' => SubmissionStatus::Approved,
                'reviewer_id' => $reviewer->id,
                'review_comment' => $comment,
                'reviewed_at' => now(),
            ])->save();

            return $tool;
        });

        return $tool;
    }

    private function create(ToolSubmission $submission): Tool
    {
        $payload = $submission->payload;
        $source = $submission->source();

        $tool = Tool::query()->create([
            'slug' => $this->uniqueSlug((string) $payload['name']),
            'kind' => $payload['kind'],
            'name' => $payload['name'],
            'summary' => $payload['summary'],
            'description' => $payload['description'] ?? null,
            'icon' => $payload['icon'],
            'accent' => $payload['accent'],
            'status' => ToolStatus::Running,
            'owner_id' => $submission->user_id,
            'department' => $payload['department'] ?? null,
            'config' => $submission->config(),
            'source' => $source,
            'source_hash' => $source === null ? null : hash('sha256', $source),
            'published_at' => now(),
        ]);

        $this->syncTags->handle($tool, $payload['categories'] ?? []);

        return $tool;
    }

    private function update(ToolSubmission $submission): Tool
    {
        $tool = $this->target($submission);
        $source = $submission->source();

        $tool->forceFill([
            'config' => $submission->config(),
            'source' => $source,
            'source_hash' => $source === null ? null : hash('sha256', $source),
            'status' => ToolStatus::Running,
            'deprecated_at' => null,
            'published_at' => now(),
        ])->save();

        return $tool;
    }

    private function deprecate(ToolSubmission $submission): Tool
    {
        $tool = $this->target($submission);

        $tool->forceFill([
            'status' => ToolStatus::Deprecated,
            'deprecated_at' => now(),
        ])->save();

        return $tool;
    }

    private function target(ToolSubmission $submission): Tool
    {
        $tool = $submission->tool;

        if ($tool === null) {
            throw new \LogicException('An update or deprecate submission must point at a tool.');
        }

        return $tool;
    }

    /**
     * Versions are the approval time to the minute, with a counter when a
     * tool is approved twice within one minute: 202608271037, then
     * 202608271037.2.
     */
    private function nextVersion(Tool $tool): string
    {
        $stamp = now()->format('YmdHi');
        $current = $tool->version;

        if ($current === null || ! str_starts_with($current, $stamp)) {
            return $stamp;
        }

        $suffix = (int) (explode('.', $current)[1] ?? 1);

        return $stamp.'.'.($suffix + 1);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'tool';
        $slug = $base;

        for ($i = 2; Tool::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
