<?php

namespace App\Models;

use App\Enums\ToolKind;
use App\Enums\ToolStatus;
use Database\Factories\ToolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A published tool: the last approved snapshot of a submission. Everything a
 * visitor can open from the catalog is a row here.
 *
 * @property int $id
 * @property string $ulid
 * @property string $slug
 * @property ToolKind $kind
 * @property string $name
 * @property string $summary
 * @property string|null $description
 * @property string $icon
 * @property string $accent
 * @property ToolStatus $status
 * @property int|null $owner_id
 * @property string|null $department
 * @property array<string, mixed> $config
 * @property string|null $source
 * @property string|null $source_hash
 * @property string|null $version
 * @property string|null $mirror_commit_sha
 * @property int|null $requested_by
 * @property int|null $endorsed_by
 * @property int|null $approved_by
 * @property int|null $approved_submission_id
 * @property Carbon|null $published_at
 * @property Carbon|null $deprecated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $owner
 * @property-read User|null $requester
 * @property-read User|null $endorser
 * @property-read User|null $approver
 * @property-read Collection<int, Tag> $tags
 */
#[Fillable([
    'ulid', 'slug', 'kind', 'name', 'summary', 'description', 'icon', 'accent', 'status',
    'owner_id', 'department', 'config', 'source', 'source_hash', 'version', 'mirror_commit_sha',
    'requested_by', 'endorsed_by', 'approved_by', 'approved_submission_id', 'published_at', 'deprecated_at',
])]
class Tool extends Model
{
    /** @use HasFactory<ToolFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /** Icons a tool may pick; mirrors TOOL_ICONS in resources/js/lib/tool-presets.ts. */
    public const ICONS = ['app-window', 'book-open', 'database', 'file-text', 'link', 'scroll-text', 'terminal', 'wrench'];

    /** Card colours a tool may pick; mirrors TOOL_ACCENTS in resources/js/lib/tool-presets.ts. */
    public const ACCENTS = ['amber', 'sky', 'emerald', 'violet', 'rose', 'slate'];

    /**
     * The tool is addressed by its ULID, never by its auto-increment id.
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
            'kind' => ToolKind::class,
            'status' => ToolStatus::class,
            'config' => 'array',
            'published_at' => 'datetime',
            'deprecated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function endorser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'endorsed_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * @return HasMany<ToolSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(ToolSubmission::class);
    }

    /**
     * @param  Builder<Tool>  $query
     */
    public function scopeRunning(Builder $query): void
    {
        $query->where('status', ToolStatus::Running);
    }

    public function isRunning(): bool
    {
        return $this->status === ToolStatus::Running;
    }

    /**
     * The URL a link or embed tool points at, as stored in its config.
     */
    public function url(): ?string
    {
        $url = $this->config['url'] ?? null;

        return is_string($url) ? $url : null;
    }

    /**
     * The URL an embed tool may frame, or null when it must not be framed.
     * Only external https origins qualify: framing our own origin would hand
     * the embedded page our DOM. Checked here as well as at submission time
     * so a bad row can never reach an iframe.
     */
    public function frameableUrl(): ?string
    {
        $url = $this->url();

        if ($url === null || ! str_starts_with($url, 'https://')) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $own = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host) && $host !== '' && $host !== $own ? $url : null;
    }

    /**
     * The category tag values, in the order they were attached.
     *
     * @return array<int, string>
     */
    public function categories(): array
    {
        return $this->tags
            ->where('group', Tag::GROUP_CATEGORY)
            ->pluck('value')
            ->values()
            ->all();
    }
}
