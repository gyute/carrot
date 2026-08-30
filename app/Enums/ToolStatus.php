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
}
