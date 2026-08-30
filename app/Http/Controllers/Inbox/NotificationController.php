<?php

namespace App\Http\Controllers\Inbox;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The bell's own actions. Everything redirects back: the panel re-renders
 * from the shared `notifications` prop.
 */
class NotificationController extends Controller
{
    public function read(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->first();

        abort_if($notification === null, 404);

        $notification->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
