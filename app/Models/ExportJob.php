<?php

namespace App\Models;

use App\Enums\ExportJobStatus;
use Database\Factories\ExportJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property string $definition
 * @property ExportJobStatus $status
 * @property string $download_code
 * @property int|null $row_count
 * @property string|null $file_path
 * @property int|null $file_size
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'definition', 'download_code'])]
class ExportJob extends Model
{
    /** @use HasFactory<ExportJobFactory> */
    use HasFactory, HasUlids;

    /**
     * The batch is addressed by its ULID, never by its auto-increment id.
     */
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
            'status' => ExportJobStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
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
     * The code handed to the requester so the file can be picked up without
     * signing in as its owner.
     */
    public static function newDownloadCode(): string
    {
        return Str::upper(Str::random(10));
    }

    /**
     * The reviewed query this batch runs, or null once it has been retired
     * from config/exports.php. Batches outlive definitions, so every caller
     * has to cope with it being gone.
     *
     * @return array{label: string, description: string, connection: string, sql: string}|null
     */
    public function definition(): ?array
    {
        $definition = config("exports.definitions.{$this->definition}");

        if (! is_array($definition)) {
            return null;
        }

        /** @var array{label: string, description: string, connection: string, sql: string} $definition */
        return $definition;
    }

    public function label(): string
    {
        return $this->definition()['label'] ?? $this->definition;
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isDownloadable(): bool
    {
        return $this->status === ExportJobStatus::Completed
            && $this->file_path !== null
            && ! $this->hasExpired();
    }

    /**
     * The name the browser saves the file under.
     */
    public function fileName(): string
    {
        return sprintf(
            '%s_%s.csv',
            $this->definition,
            ($this->completed_at ?? $this->created_at ?? now())->format('Ymd_His'),
        );
    }
}
