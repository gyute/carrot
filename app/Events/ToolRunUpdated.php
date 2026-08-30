<?php

namespace App\Events;

use App\Models\ToolRun;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pushed to the person who started a run each time its status changes, so
 * the run screen refreshes without polling.
 */
class ToolRunUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ToolRun $run) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->run->user_id)];
    }

    /**
     * @return array{ulid: string, status: string}
     */
    public function broadcastWith(): array
    {
        return ['ulid' => $this->run->ulid, 'status' => $this->run->status->value];
    }
}
