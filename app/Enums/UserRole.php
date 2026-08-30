<?php

namespace App\Enums;

enum UserRole: string
{
    /** Uses tools and requests them. */
    case Member = 'member';

    /** Approves requests from their own department (first stage). */
    case Manager = 'manager';

    /** System administrator: confirms endorsed requests (second stage) and manages tools directly. */
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Member => 'メンバー',
            self::Manager => '部署管理者',
            self::Admin => 'システム管理者',
        };
    }
}
