<?php

namespace App\Jobs;

use App\Enums\ExportJobStatus;
use App\Models\ExportJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Runs one reviewed query and writes the result to a private CSV file. The
 * requester picks the file up later from the batch list, so nothing here is
 * allowed to fail loudly: a broken query is recorded on the batch itself.
 */
class RunExportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public ExportJob $exportJob) {}

    public function handle(): void
    {
        $this->exportJob->forceFill([
            'status' => ExportJobStatus::Running,
            'started_at' => now(),
        ])->save();

        try {
            [$path, $rows, $size] = $this->write();
        } catch (Throwable $e) {
            Log::error('Export batch failed.', ['ulid' => $this->exportJob->ulid, 'exception' => $e]);

            $this->fail($e);

            return;
        }

        $this->exportJob->forceFill([
            'status' => ExportJobStatus::Completed,
            'row_count' => $rows,
            'file_path' => $path,
            'file_size' => $size,
            'completed_at' => now(),
            'expires_at' => now()->addDays((int) config('exports.retention_days')),
        ])->save();
    }

    public function failed(?Throwable $e): void
    {
        $this->fail($e);
    }

    /**
     * Stream the query into a CSV file and return its path, row count and size.
     *
     * @return array{0: string, 1: int, 2: int}
     */
    private function write(): array
    {
        $definition = $this->exportJob->definition();
        $disk = Storage::disk(config('exports.disk'));
        $path = config('exports.directory').'/'.$this->exportJob->ulid.'.csv';

        $handle = fopen('php://temp/maxmemory:'.(4 * 1024 * 1024), 'w+b');

        if ($handle === false) {
            throw new \RuntimeException('Unable to open a buffer for the export.');
        }

        // Excel on Windows reads UTF-8 CSV as Shift_JIS without this marker.
        fwrite($handle, "\xEF\xBB\xBF");

        $rows = 0;

        foreach (DB::connection($definition['connection'])->cursor($definition['sql']) as $record) {
            /** @var array<string, mixed> $row */
            $row = (array) $record;

            if ($rows === 0) {
                fputcsv($handle, array_keys($row), escape: '');
            }

            fputcsv($handle, array_map($this->stringify(...), $row), escape: '');
            $rows++;
        }

        rewind($handle);
        $disk->writeStream($path, $handle);
        $size = (int) $disk->size($path);
        fclose($handle);

        return [$path, $rows, $size];
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => json_encode($value, JSON_UNESCAPED_UNICODE) ?: '',
        };
    }

    private function fail(?Throwable $e): void
    {
        $this->exportJob->forceFill([
            'status' => ExportJobStatus::Failed,
            'error_message' => $e?->getMessage(),
            'completed_at' => now(),
        ])->save();
    }
}
