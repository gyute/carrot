<?php

namespace App\Jobs;

use App\Models\Tool;
use App\Support\Github\GitHub;
use App\Support\Github\ToolDocument;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Writes what a tool is *now* to the mirror repository.
 *
 * State, not events: the job re-reads the row rather than being handed a
 * change. Eight code paths write to `tools` and the count keeps going up, so
 * anything that hooks each one in turn is a list somebody will forget to add
 * to. Re-reading also settles ordering for free - ToolController@update syncs
 * tags after saving the row, and by the time this runs both are in.
 */
class MirrorToolToRepo implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * The slug travels with the ULID because a purged tool has no row left to
     * read it from, and the directory still has to go.
     */
    public function __construct(public string $ulid, public string $slug) {}

    /**
     * A burst of changes to one tool collapses into one write. The lock is
     * released when the job starts rather than when it finishes: a change
     * made while a write is in flight has to queue another one, or the
     * mirror would stop at the state it happened to read.
     */
    public function uniqueId(): string
    {
        return $this->ulid;
    }

    /**
     * Seconds between attempts. A push that lost a race with another commit
     * only needs the branch to settle.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 15, 60, 300];
    }

    public function handle(GitHub $github): void
    {
        if (! GitHub::enabled()) {
            return;
        }

        $tool = Tool::withTrashed()
            ->with(['tags', 'owner', 'requester', 'endorser', 'approver'])
            ->where('ulid', $this->ulid)
            ->first();

        // Purged: nothing to describe, so the directory goes.
        if ($tool === null) {
            $github->commit([], [config('github.path').'/'.$this->slug], "Purge {$this->slug}\n");

            return;
        }

        $document = new ToolDocument($tool);

        $github->commit($document->files(), [], $document->message());
    }
}
