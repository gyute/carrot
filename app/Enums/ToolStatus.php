<?php

namespace App\Enums;

/**
 * The state of a published tool. A tool that is only requested and not yet
 * approved has no row in `tools` at all; the catalog shows it as `pending`
 * from its submission instead.
 */
enum ToolStatus: string
{
    case Running = 'running';
    case Deprecated = 'deprecated';

    public function label(): string
    {
        return match ($this) {
            self::Running => '稼働中',
            self::Deprecated => '非推奨',
        };
    }
}
