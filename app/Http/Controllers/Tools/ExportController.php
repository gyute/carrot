<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tools\ExportStoreRequest;
use App\Jobs\RunExportJob;
use App\Models\ExportJob;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ExportController extends Controller
{
    /**
     * Show the export definitions a user may run.
     */
    public function create(): Response
    {
        /** @var array<string, array{label: string, description: string}> $definitions */
        $definitions = config('exports.definitions', []);

        return Inertia::render('tools/exports/create', [
            'definitions' => collect($definitions)
                ->map(fn (array $definition, string $key): array => [
                    'key' => $key,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Queue an export and hand the requester its download code.
     */
    public function store(ExportStoreRequest $request): RedirectResponse
    {
        $exportJob = ExportJob::create([
            'user_id' => $request->user()->id,
            'definition' => $request->string('definition')->value(),
            'download_code' => ExportJob::newDownloadCode(),
        ]);

        RunExportJob::dispatch($exportJob);

        return to_route('tools.exports.jobs')->with('issuedCode', $exportJob->download_code);
    }
}
