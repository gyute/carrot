<?php

namespace App\Enums;

/**
 * Set by the development team when they accept a request, not by the
 * requester - everyone's own work is urgent.
 */
enum ToolRequestPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => '低',
            self::Normal => '中',
            self::High => '高',
        };
    }
}
