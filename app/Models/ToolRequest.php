<?php

namespace App\Models;

use App\Enums\ToolKind;
use App\Enums\ToolRequestPriority;
use App\Enums\ToolRequestStatus;
use Database\Factories\ToolRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A request for a tool that does not exist yet. Unlike a submission it carries
 * nothing runnable - the development team reads it and builds the tool, and
 * the submission that publishes it closes this request.
 *
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property ToolRequestStatus $status
 * @property string $title
 * @property string $body
 * @property string|null $department
 * @property array<int, string> $categories
 * @property ToolKind|null $desired_kind
 * @property Carbon|null $needed_by
 * @property ToolRequestPriority|null $priority
 * @property int|null $assignee_id
 * @property int|null $decided_by
 * @property string|null $decision_comment
 * @property Carbon|null $decided_at
 * @property int|null $tool_id
 * @property int|null $duplicate_of_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read User|null $assignee
 * @property-read User|null $decider
 * @property-read Tool|null $tool
 * @property-read ToolRequest|null $duplicateOf
 */
#[Fillable([
    'user_id', 'status', 'title', 'body', 'department', 'categories', 'desired_kind',
    'needed_by', 'priority', 'assignee_id', 'decided_by', 'decision_comment', 'decided_at',
    'tool_id', 'duplicate_of_id',
])]
class ToolRequest extends Model
{
    /** @use HasFactory<ToolRequestFactory> */
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
            'status' => ToolRequestStatus::class,
            'categories' => 'array',
            'desired_kind' => ToolKind::class,
            'priority' => ToolRequestPriority::class,
            'needed_by' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by')->withTrashed();
    }

    /**
     * @return BelongsTo<Tool, $this>
     */
    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    /**
     * @return BelongsTo<ToolRequest, $this>
     */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(ToolRequest::class, 'duplicate_of_id');
    }

    /**
     * A request is its department's business: the requester, their colleagues
     * and the development team see it, nobody else. A request with no
     * department has no colleagues, so it stays with its requester.
     *
     * @param  Builder<ToolRequest>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $query->where(function (Builder $query) use ($user): void {
            $query->where('user_id', $user->id);

            if ($user->department !== null) {
                $query->orWhere('department', $user->department);
            }
        });
    }

    /**
     * What the development team still has to look at.
     *
     * @param  Builder<ToolRequest>  $query
     */
    public function scopeAwaitingTriage(Builder $query): void
    {
        $query->whereIn('status', ToolRequestStatus::awaitingTriage());
    }

    /**
     * @param  Builder<ToolRequest>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereIn('status', ToolRequestStatus::live());
    }
}
