<?php

namespace App\Support;

use App\Models\User;

/**
 * Which halves of the tool module this deployment runs. A portal may take
 * requests without letting everyone register tools, register tools without
 * taking requests, or do neither and stay a fixed catalog.
 */
class Features
{
    /** Nobody may register a tool, so the catalog only ever shrinks. */
    public const SUBMISSIONS_NONE = 'none';

    /** Only the development team registers tools; everyone else asks. */
    public const SUBMISSIONS_ADMIN = 'admin';

    /** Anyone may register a tool for their department. */
    public const SUBMISSIONS_ALL = 'all';

    public static function submissionMode(): string
    {
        $mode = (string) config('catalog.features.submissions', self::SUBMISSIONS_ALL);

        return in_array($mode, [self::SUBMISSIONS_NONE, self::SUBMISSIONS_ADMIN, self::SUBMISSIONS_ALL], true)
            ? $mode
            : self::SUBMISSIONS_ALL;
    }

    /**
     * Whether the submission and approval screens exist at all. Off means
     * gone - the routes 404 - so a portal that never reviews anything is not
     * showing people a door they cannot open.
     */
    public static function submissions(): bool
    {
        return self::submissionMode() !== self::SUBMISSIONS_NONE;
    }

    /**
     * Whether this person may file one. Separate from the question above:
     * in `admin` mode the screens exist and everyone keeps their history,
     * but only the development team opens a new request.
     */
    public static function maySubmit(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return match (self::submissionMode()) {
            self::SUBMISSIONS_NONE => false,
            self::SUBMISSIONS_ADMIN => $user->isAdmin(),
            default => true,
        };
    }

    public static function requests(): bool
    {
        return (bool) config('catalog.features.requests', true);
    }

    /**
     * The name a route's `feature:` middleware passes in.
     */
    public static function enabled(string $feature): bool
    {
        return match ($feature) {
            'submissions' => self::submissions(),
            'requests' => self::requests(),
            default => false,
        };
    }
}
