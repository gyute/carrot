<?php

namespace App\Http\Controllers\Inbox;

use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    public function index(Request $request): Response
    {
        $unreadOnly = $request->boolean('unread');

        $messages = Message::query()
            ->with('sender')
            ->where('recipient_id', $request->user()->id)
            ->when($unreadOnly, fn ($query) => $query->unread())
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('inbox/index', [
            'messages' => $messages->through($this->present(...)),
            'unreadOnly' => $unreadOnly,
            'unreadCount' => Message::query()->where('recipient_id', $request->user()->id)->unread()->count(),
        ]);
    }

    /**
     * Opening a message reads it, and reads the bell notification that
     * pointed at it.
     */
    public function show(Request $request, Message $message): Response
    {
        abort_unless($message->recipient_id === $request->user()->id, 404);

        $message->markRead();

        $request->user()->unreadNotifications()
            ->where('data->message', $message->ulid)
            ->update(['read_at' => now()]);

        return Inertia::render('inbox/show', [
            'message' => $this->present($message->load('sender')),
        ]);
    }

    public function read(Request $request, Message $message): RedirectResponse
    {
        abort_unless($message->recipient_id === $request->user()->id, 404);

        $message->markRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        Message::query()
            ->where('recipient_id', $request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return back()->with('status', 'すべて既読にしました。');
    }

    /**
     * @return array{ulid: string, kind: string, subject: string, body: string, sender: string|null, actionUrl: string|null, actionLabel: string|null, read: bool, createdAt: string}
     */
    private function present(Message $message): array
    {
        return [
            'ulid' => $message->ulid,
            'kind' => $message->kind->value,
            'subject' => $message->subject,
            'body' => $message->body,
            'sender' => $message->sender?->name,
            'actionUrl' => $message->action_url,
            'actionLabel' => $message->action_label,
            'read' => $message->isRead(),
            'createdAt' => $message->created_at?->toIso8601String() ?? '',
        ];
    }
}
