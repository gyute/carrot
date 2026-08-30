<?php

namespace App\Enums;

enum ToolRunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case TimedOut = 'timed_out';

    public function label(): string
    {
        return match ($this) {
            self::Queued => '待機中',
            self::Running => '実行中',
            self::Completed => '完了',
            self::Failed => '失敗',
            self::TimedOut => 'タイムアウト',
        };
    }

    public function isFinished(): bool
    {
        return ! in_array($this, [self::Queued, self::Running], true);
    }
}
