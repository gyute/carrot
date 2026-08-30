<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case Draft = 'draft';

    /** Submitted and waiting for a reviewer. */
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Draft => '下書き',
            self::Pending => '承認待ち',
            self::Approved => '承認済み',
            self::Rejected => '差し戻し',
            self::Withdrawn => '取り下げ',
        };
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
        return in_array($this, [self::Draft, self::Pending], true);
    }

    /**
     * Whether a reviewer still has to act on it.
     */
    public function isAwaitingReview(): bool
    {
        return $this === self::Pending;
    }
}
