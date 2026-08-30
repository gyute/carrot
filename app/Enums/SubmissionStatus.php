<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case Draft = 'draft';

    /** Waiting for the requester's department manager. */
    case Pending = 'pending';

    /** Endorsed by the department; waiting for a system administrator. */
    case Endorsed = 'endorsed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Draft => '下書き',
            self::Pending => '部署承認待ち',
            self::Endorsed => 'システム確認待ち',
            self::Approved => '承認済み',
            self::Rejected => '差し戻し',
            self::Withdrawn => '取り下げ',
        };
    }

    /**
     * Not decided yet, at either stage. The one definition every query and
     * check reads, so a third stage would only be added here.
     *
     * @return array<int, self>
     */
    public static function awaitingReview(): array
    {
        return [self::Pending, self::Endorsed];
    }

    /**
     * Still the requester's to withdraw: waiting, or not yet sent.
     *
     * @return array<int, self>
     */
    public static function open(): array
    {
        return [self::Draft, ...self::awaitingReview()];
    }

    /**
     * Whether the requester may still change or submit it.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }

    public function isAwaitingReview(): bool
    {
        return in_array($this, self::awaitingReview(), true);
    }
}
