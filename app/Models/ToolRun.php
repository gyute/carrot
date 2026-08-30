<?php

namespace App\Models;

use App\Enums\ToolRunStatus;
use Database\Factories\ToolRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One execution of a script tool in the sandbox. Points at the tool for a
 * normal run, or at the submission for an admin's test run before approval.
 *
 * @property int $id
 * @property string $ulid
 * @property int|null $tool_id
 * @property int|null $tool_submission_id
 * @property int $user_id
 * @property string $runtime
 * @property string $source_hash
 * @property ToolRunStatus $status
 * @property array<string, mixed> $inputs
 * @property int|null $exit_code
 * @property string|null $stdout
 * @property string|null $stderr
 * @property bool $truncated
 * @property int|null $duration_ms
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tool|null $tool
 * @property-read ToolSubmission|null $submission
 * @property-read User $user
 */
#[Fillable(['tool_id', 'tool_submission_id', 'user_id', 'runtime', 'source_hash', 'status', 'inputs'])]
class ToolRun extends Model
{
    /** @use HasFactory<ToolRunFactory> */
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
            'status' => ToolRunStatus::class,
            'inputs' => 'array',
            'truncated' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tool, $this>
     */
    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    /**
     * @return BelongsTo<ToolSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(ToolSubmission::class, 'tool_submission_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFinished(): bool
    {
        return $this->status->isFinished();
    }
}
