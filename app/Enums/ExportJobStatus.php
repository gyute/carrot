<?php

namespace App\Enums;

enum ExportJobStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * The Japanese label shown in the batch list.
     */
    public function label(): string
    {
        return match ($this) {
            self::Queued => '待機中',
            self::Running => '実行中',
            self::Completed => '完了',
            self::Failed => '失敗',
        };
    }

    /**
     * Whether the batch has finished, successfully or not.
     */
    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
    }
}
