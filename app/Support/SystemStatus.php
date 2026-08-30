<?php

namespace App\Support;

use App\Enums\ToolRunStatus;
use App\Models\ToolRun;
use App\Sandbox\RuntimeLabels;
use App\Sandbox\SandboxRunner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * What an admin needs to know about the moving parts: are the queue workers
 * alive, is anything stuck or failing, is the sandbox ready, is Reverb up,
 * and what the application log said last. Reads state rather than process
 * output, so it means the same thing on a dev box and in production.
 */
class SystemStatus
{
    /** A worker that has not looped for this long is presumed dead. */
    public const HEARTBEAT_STALE_SECONDS = 90;

    public const QUEUES = ['default', 'sandbox'];

    public function __construct(private RuntimeLabels $runtimes) {}

    /**
     * Called from the worker's `Looping` event so each queue leaves a pulse.
     */
    public static function heartbeat(string $queue): void
    {
        Cache::put("worker:heartbeat:{$queue}", now()->toIso8601String(), self::HEARTBEAT_STALE_SECONDS * 4);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'queues' => $this->queues(),
            'failedJobs' => $this->failedJobs(),
            'sandbox' => $this->sandbox(),
            'reverb' => $this->reverb(),
            'runs' => $this->runs(),
            'log' => $this->logTail(),
            'checkedAt' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array{name: string, pending: int, reserved: int, oldestPendingSeconds: int|null, heartbeatAt: string|null, alive: bool}>
     */
    private function queues(): array
    {
        // Counts come from the database queue table; on another queue
        // driver they read zero and the heartbeat is what matters.
        $listens = Schema::hasTable('jobs');

        return array_map(function (string $queue) use ($listens): array {
            $pending = $listens ? DB::table('jobs')->where('queue', $queue)->whereNull('reserved_at')->count() : 0;
            $reserved = $listens ? DB::table('jobs')->where('queue', $queue)->whereNotNull('reserved_at')->count() : 0;
            $oldest = $listens ? DB::table('jobs')->where('queue', $queue)->whereNull('reserved_at')->min('available_at') : null;
            $beat = Cache::get("worker:heartbeat:{$queue}");
            $beatAt = is_string($beat) ? Carbon::parse($beat) : null;

            return [
                'name' => $queue,
                'pending' => $pending,
                'reserved' => $reserved,
                'oldestPendingSeconds' => is_numeric($oldest) ? max(0, now()->getTimestamp() - (int) $oldest) : null,
                'heartbeatAt' => $beatAt?->toIso8601String(),
                'alive' => $beatAt !== null && $beatAt->gt(now()->subSeconds(self::HEARTBEAT_STALE_SECONDS)),
            ];
        }, self::QUEUES);
    }

    /**
     * @return array{count: int, recent: array<int, array{id: int, queue: string, job: string, failedAt: string, exception: string}>}
     */
    private function failedJobs(): array
    {
        $rows = DB::table('failed_jobs')->latest('failed_at')->limit(10)->get();

        return [
            'count' => DB::table('failed_jobs')->count(),
            'recent' => $rows->map(function (object $row): array {
                $payload = json_decode((string) $row->payload, true);

                return [
                    'id' => (int) $row->id,
                    'queue' => (string) $row->queue,
                    'job' => (string) ($payload['displayName'] ?? '?'),
                    'failedAt' => Carbon::parse($row->failed_at)->toIso8601String(),
                    'exception' => mb_strimwidth((string) strtok((string) $row->exception, "\n"), 0, 200, '…'),
                ];
            })->all(),
        ];
    }

    /**
     * @return array{driver: string, ready: bool, message: string|null, runtimes: array<string, string>, requireRootless: bool}
     */
    private function sandbox(): array
    {
        $driver = (string) config('sandbox.driver');
        $ready = false;
        $message = null;

        try {
            app(SandboxRunner::class)->ensureReady();
            $ready = true;
        } catch (Throwable $e) {
            $message = $e->getMessage();
        }

        return [
            'driver' => $driver,
            'ready' => $ready,
            'message' => $message,
            'runtimes' => $this->runtimes->all(),
            'requireRootless' => (bool) config('sandbox.require_rootless'),
        ];
    }

    /**
     * A TCP knock on the Reverb port: cheap, and enough to tell "not running"
     * from "running".
     *
     * @return array{connection: string, host: string|null, port: int|null, up: bool|null}
     */
    private function reverb(): array
    {
        $connection = (string) config('broadcasting.default');

        if ($connection !== 'reverb') {
            return ['connection' => $connection, 'host' => null, 'port' => null, 'up' => null];
        }

        $host = (string) config('reverb.servers.reverb.host', '0.0.0.0');
        $port = (int) config('reverb.servers.reverb.port', 8080);
        $target = in_array($host, ['0.0.0.0', '::'], true) ? '127.0.0.1' : $host;

        $socket = @fsockopen($target, $port, $errno, $error, 1.0);
        $up = is_resource($socket);

        if ($up) {
            fclose($socket);
        }

        return ['connection' => $connection, 'host' => $target, 'port' => $port, 'up' => $up];
    }

    /**
     * @return array{running: int, last24h: array<string, int>, recent: array<int, array{ulid: string, tool: string|null, toolUlid: string|null, status: string, statusLabel: string, user: string, durationMs: int|null, createdAt: string}>}
     */
    private function runs(): array
    {
        $recent = ToolRun::query()->with(['tool', 'user'])->latest()->limit(10)->get();

        $counts = ToolRun::query()
            ->where('created_at', '>=', now()->subDay())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'running' => ToolRun::query()->whereIn('status', [ToolRunStatus::Queued, ToolRunStatus::Running])->count(),
            'last24h' => collect(ToolRunStatus::cases())
                ->mapWithKeys(fn (ToolRunStatus $status): array => [$status->value => (int) ($counts[$status->value] ?? 0)])
                ->all(),
            'recent' => $recent->map(fn (ToolRun $run): array => [
                'ulid' => $run->ulid,
                'tool' => $run->tool?->name,
                'toolUlid' => $run->tool?->ulid,
                'status' => $run->status->value,
                'statusLabel' => $run->status->label(),
                'user' => $run->user->name,
                'durationMs' => $run->duration_ms,
                'createdAt' => $run->created_at?->toIso8601String() ?? '',
            ])->all(),
        ];
    }

    /**
     * The last lines of the application log, newest last.
     *
     * @return array{path: string|null, lines: array<int, string>}
     */
    private function logTail(int $lines = 80): array
    {
        $path = storage_path('logs/laravel.log');

        if (! File::exists($path)) {
            return ['path' => null, 'lines' => []];
        }

        $size = File::size($path);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return ['path' => $path, 'lines' => []];
        }

        // Read the last 64KB and keep whole lines only.
        $chunk = 65536;
        fseek($handle, max(0, $size - $chunk));
        $tail = (string) stream_get_contents($handle);
        fclose($handle);

        $all = preg_split('/\R/', rtrim($tail)) ?: [];

        if ($size > $chunk) {
            array_shift($all);
        }

        return ['path' => $path, 'lines' => array_slice($all, -$lines)];
    }
}
