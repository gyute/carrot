<?php

namespace App\Enums;

enum SubmissionAction: string
{
    /** Register a new tool. */
    case Create = 'create';

    /** Change how a published tool behaves: its config or source. */
    case Update = 'update';

    /** Retire a published tool. */
    case Deprecate = 'deprecate';

    public function label(): string
    {
        return match ($this) {
            self::Create => '新規登録',
            self::Update => '更新',
            self::Deprecate => '非推奨化',
        };
    }
}
