<?php

namespace App\Enums;

enum ToolRequestStatus: string
{
    /** Filed, waiting for the development team to look at it. */
    case Open = 'open';

    /** The development team took it on. */
    case Accepted = 'accepted';

    case InProgress = 'in_progress';

    /** A tool was published for it. */
    case Delivered = 'delivered';

    case Declined = 'declined';

    /** Folded into another request, which `duplicate_of_id` points at. */
    case Duplicate = 'duplicate';

    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Open => '受付中',
            self::Accepted => '対応予定',
            self::InProgress => '対応中',
            self::Delivered => '公開済み',
            self::Declined => '見送り',
            self::Duplicate => '重複',
            self::Withdrawn => '取り下げ',
        };
    }

    /**
     * Not finished with: still the development team's to move along. The one
     * definition every query and check reads.
     *
     * @return array<int, self>
     */
    public static function live(): array
    {
        return [self::Open, self::Accepted, self::InProgress];
    }

    /**
     * Nobody has triaged it yet, so it is what the development team's queue
     * counts.
     *
     * @return array<int, self>
     */
    public static function awaitingTriage(): array
    {
        return [self::Open];
    }

    public function isLive(): bool
    {
        return in_array($this, self::live(), true);
    }

    /**
     * Whether the requester may still change the wording. Once the team has
     * taken it on, the text they read must stop moving.
     */
    public function isEditable(): bool
    {
        return $this === self::Open;
    }
}
