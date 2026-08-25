<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class StudioController extends Controller
{
    /**
     * Show one of the allowlisted pages inside the studio frame.
     */
    public function show(?string $page = null): Response
    {
        /** @var array<string, array{label: string, description: string, url: string}> $pages */
        $pages = config('tools.studio', []);

        $key = $page ?? array_key_first($pages);

        abort_if($key === null || ! isset($pages[$key]), 404);

        return Inertia::render('tools/studio', [
            'pages' => collect($pages)
                ->map(fn (array $page, string $key): array => [
                    'key' => $key,
                    'label' => $page['label'],
                    'description' => $page['description'],
                ])
                ->values()
                ->all(),
            'current' => [
                'key' => $key,
                'label' => $pages[$key]['label'],
                'description' => $pages[$key]['description'],
                'url' => $pages[$key]['url'],
            ],
        ]);
    }
}
