<?php

namespace App\Enums;

enum ToolKind: string
{
    /** Opens a URL, internal or external, in the current tab. */
    case Link = 'link';

    /** Frames an external page inside the studio screen. */
    case Embed = 'embed';

    /** Runs a PHP or shell script in the sandbox. */
    case Script = 'script';

    /**
     * The Japanese label shown in forms and on the detail screen.
     */
    public function label(): string
    {
        return match ($this) {
            self::Link => 'リンク',
            self::Embed => '埋め込み',
            self::Script => 'スクリプト',
        };
    }
}
