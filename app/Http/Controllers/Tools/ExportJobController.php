<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tools\ExportJobLookupRequest;
use App\Models\ExportJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportJobController extends Controller
{
    /**
     * The session key holding the download codes entered in this session.
     */
    private const CODES = 'tools.export_codes';

    /**
     * List the batches the visitor may see: their own, plus any unlocked with
     * a download code during this session.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $own = ExportJob::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(50)
            ->get();

        $unlocked = ExportJob::query()
            ->whereIn('download_code', $this->unlockedCodes($request))
            ->where('user_id', '!=', $user->id)
            ->latest()
            ->get();

        return Inertia::render('tools/exports/jobs', [
            'jobs' => $own->map($this->present(...))->all(),
            'unlockedJobs' => $unlocked->map($this->present(...))->all(),
            'issuedCode' => $request->session()->get('issuedCode'),
        ]);
    }

    /**
     * Unlock a batch with the requester's own employee ID or a download code.
     */
    public function lookup(ExportJobLookupRequest $request): RedirectResponse
    {
        $key = $request->string('key')->trim()->value();
        $user = $request->user();

        if (mb_strtolower($key) === $user->username) {
            return to_route('tools.exports.jobs');
        }

        $exportJob = ExportJob::query()
            ->where('download_code', mb_strtoupper($key))
            ->first();

        if ($exportJob === null) {
            throw ValidationException::withMessages([
                'key' => '該当するバッチが見つかりません。社員 ID またはダウンロードコードを確認してください。',
            ]);
        }

        $request->session()->put(self::CODES, array_values(array_unique([
            ...$this->unlockedCodes($request),
            $exportJob->download_code,
        ])));

        return to_route('tools.exports.jobs');
    }

    /**
     * Send the generated CSV to a requester allowed to have it.
     */
    public function download(Request $request, ExportJob $exportJob): StreamedResponse
    {
        abort_unless($this->mayDownload($request, $exportJob), 403);
        abort_unless($exportJob->isDownloadable(), 404);

        return Storage::disk(config('exports.disk'))
            ->download((string) $exportJob->file_path, $exportJob->fileName());
    }

    /**
     * The owner always may; anyone else needs the code for this batch.
     */
    private function mayDownload(Request $request, ExportJob $exportJob): bool
    {
        return $exportJob->user_id === $request->user()->id
            || in_array($exportJob->download_code, $this->unlockedCodes($request), true);
    }

    /**
     * @return array<int, string>
     */
    private function unlockedCodes(Request $request): array
    {
        /** @var array<int, string> $codes */
        $codes = $request->session()->get(self::CODES, []);

        return $codes;
    }

    /**
     * @return array{ulid: string, label: string, status: string, statusLabel: string, rowCount: int|null, downloadCode: string, requestedBy: string, createdAt: string, completedAt: string|null, expiresAt: string|null, errorMessage: string|null, downloadable: bool}
     */
    private function present(ExportJob $exportJob): array
    {
        return [
            'ulid' => $exportJob->ulid,
            'label' => $exportJob->label(),
            'status' => $exportJob->status->value,
            'statusLabel' => $exportJob->status->label(),
            'rowCount' => $exportJob->row_count,
            'downloadCode' => $exportJob->download_code,
            'requestedBy' => $exportJob->user->name,
            'createdAt' => (string) $exportJob->created_at?->toIso8601String(),
            'completedAt' => $exportJob->completed_at?->toIso8601String(),
            'expiresAt' => $exportJob->expires_at?->toIso8601String(),
            'errorMessage' => $exportJob->error_message,
            'downloadable' => $exportJob->isDownloadable(),
        ];
    }
}
