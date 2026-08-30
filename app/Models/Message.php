<?php

namespace App\Models;

use App\Enums\MessageKind;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A message in someone's inbox. Unlike a notification, which is a one-line
 * event behind the bell, a message has a body and stays until deleted.
 *
 * @property int $id
 * @property string $ulid
 * @property int $recipient_id
 * @property int|null $sender_id
 * @property MessageKind $kind
 * @property string $subject
 * @property string $body
 * @property string|null $action_url
 * @property string|null $action_label
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $recipient
 * @property-read User|null $sender
 * @property-read Model|null $about
 */
#[Fillable(['recipient_id', 'sender_id', 'kind', 'subject', 'body', 'action_url', 'action_label', 'subject_type', 'subject_id', 'read_at'])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
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
            'kind' => MessageKind::class,
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id')->withTrashed();
    }

    /**
     * What the message is about, e.g. the submission it announces.
     *
     * @return MorphTo<Model, $this>
     */
    public function about(): MorphTo
    {
        return $this->morphTo('subject');
    }

    /**
     * @param  Builder<Message>  $query
     */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markRead(): void
    {
        if (! $this->isRead()) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }
}
