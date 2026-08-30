<?php

namespace App\Models;

use App\Enums\SubmissionAction;
use App\Enums\SubmissionStatus;
use Database\Factories\ToolSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A request to register, change or retire a tool. The payload is a snapshot
 * of what the tool should become; approving copies it onto the tool row.
 *
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property int|null $tool_id
 * @property SubmissionAction $action
 * @property SubmissionStatus $status
 * @property array<string, mixed> $payload
 * @property string|null $note
 * @property int|null $reviewer_id
 * @property string|null $review_comment
 * @property Carbon|null $submitted_at
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Tool|null $tool
 * @property-read User|null $reviewer
 */
#[Fillable(['user_id', 'tool_id', 'action', 'status', 'payload', 'note', 'reviewer_id', 'review_comment', 'submitted_at', 'reviewed_at'])]
class ToolSubmission extends Model
{
    /** @use HasFactory<ToolSubmissionFactory> */
    use HasFactory, HasUlids;

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => SubmissionAction::class,
            'status' => SubmissionStatus::class,
            'payload' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Tool, $this>
     */
    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * Requests a reviewer still has to act on.
     *
     * @param  Builder<ToolSubmission>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', SubmissionStatus::Pending);
    }

    /**
     * The department the request belongs to: the payload's for a new tool,
     * the tool's otherwise.
     */
    public function department(): ?string
    {
        $department = $this->payload['department'] ?? null;

        return is_string($department) && $department !== '' ? $department : $this->tool?->department;
    }

    /**
     * The name the request is shown under: the payload's for a new tool, the
     * tool's otherwise.
     */
    public function displayName(): string
    {
        $name = $this->payload['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : ($this->tool->name ?? '(無題)');
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        $config = $this->payload['config'] ?? [];

        return is_array($config) ? $config : [];
    }

    public function source(): ?string
    {
        $source = $this->payload['source'] ?? null;

        return is_string($source) && $source !== '' ? $source : null;
    }
}
