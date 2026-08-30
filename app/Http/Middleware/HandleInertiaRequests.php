<?php

namespace App\Http\Middleware;

use App\Models\ToolSubmission;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'flash' => [
                'status' => fn (): ?string => $request->session()->get('status'),
            ],
            // The bell: unread count and the latest few, on every page.
            'notifications' => fn (): array => $user === null ? ['unread' => 0, 'recent' => []] : [
                'unread' => $user->unreadNotifications()->count(),
                'recent' => $user->notifications()
                    ->latest()
                    ->limit(10)
                    ->get()
                    ->map(fn ($notification): array => [
                        'id' => $notification->id,
                        'title' => $notification->data['title'] ?? '',
                        'body' => $notification->data['body'] ?? '',
                        'url' => $notification->data['url'] ?? null,
                        'read' => $notification->read_at !== null,
                        'createdAt' => $notification->created_at?->toIso8601String(),
                    ])
                    ->all(),
            ],
            // Reviewers see what awaits them on the approvals tab.
            'pendingApprovals' => fn (): int => $user?->isReviewer()
                ? ToolSubmission::query()->awaitingReviewBy($user)->count()
                : 0,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
