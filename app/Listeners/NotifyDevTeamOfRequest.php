<?php

namespace App\Listeners;

use App\Enums\MessageKind;
use App\Events\MessageReceived;
use App\Events\ToolRequestSubmitted;
use App\Models\Message;
use App\Models\ToolRequest;
use App\Models\User;
use App\Notifications\ToolRequestFiled;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * A request goes to the development team - the administrators today - with a
 * link to the triage screen. The requester's department managers are copied
 * so they can see what their own people are asking for; they have nothing to
 * decide here, unlike the submission flow.
 */
class NotifyDevTeamOfRequest implements ShouldQueue
{
    public function handle(ToolRequestSubmitted $event): void
    {
        $toolRequest = $event->toolRequest->load('user');

        User::query()->developmentTeam()->each(function (User $admin) use ($toolRequest): void {
            $message = Message::query()->create([
                'recipient_id' => $admin->id,
                'sender_id' => $toolRequest->user_id,
                'kind' => MessageKind::RequestFiled,
                'subject' => "【依頼】{$toolRequest->title}",
                'body' => $this->body($toolRequest),
                'action_url' => route('admin.requests.show', $toolRequest, absolute: false),
                'action_label' => '依頼を開く',
                'subject_type' => ToolRequest::class,
                'subject_id' => $toolRequest->id,
            ]);

            MessageReceived::dispatch($message);

            $admin->notify(new ToolRequestFiled($toolRequest, $message));
        });

        User::query()->managersOf($toolRequest->department)->each(function (User $manager) use ($toolRequest): void {
            if ($manager->isAdmin()) {
                return;
            }

            $message = Message::query()->create([
                'recipient_id' => $manager->id,
                'sender_id' => $toolRequest->user_id,
                'kind' => MessageKind::RequestFiled,
                'subject' => "【部署の依頼】{$toolRequest->title}",
                'body' => "{$toolRequest->user->name} さんが開発チームにツールを依頼しました。\n参考までにお知らせします。判断は開発チームが行います。",
                'action_url' => route('tools.requests.show', $toolRequest, absolute: false),
                'action_label' => '依頼を開く',
                'subject_type' => ToolRequest::class,
                'subject_id' => $toolRequest->id,
            ]);

            MessageReceived::dispatch($message);
        });
    }

    private function body(ToolRequest $toolRequest): string
    {
        $who = $toolRequest->department !== null && $toolRequest->department !== ''
            ? "{$toolRequest->department} の {$toolRequest->user->name} さん"
            : "{$toolRequest->user->name} さん";

        $lines = [
            "{$who}がツールを依頼しました。",
            '',
            $toolRequest->body,
        ];

        if ($toolRequest->needed_by !== null) {
            $lines[] = '';
            $lines[] = "希望時期: {$toolRequest->needed_by->toDateString()}";
        }

        return implode("\n", $lines);
    }
}
