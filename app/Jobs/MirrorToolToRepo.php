<?php

namespace App\Jobs;

use App\Models\Tool;
use App\Support\Github\GitHub;
use App\Support\Github\ToolDocument;
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
class MirrorToolToRepo implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * Dispatched only once the surrounding transaction commits, so the job
     * never reads a row that is about to be rolled back.
     *
     * Set here rather than as a property default: the Queueable trait already
     * declares it without one, and PHP calls differing defaults a conflict.
     *
     * There is deliberately no unique lock. ShouldBeUnique takes one at
     * dispatch, inside whatever transaction is open, and ApproveSubmission
     * saves a tool twice in one - the second lock insert fails on the
     * duplicate key, Postgres ends the transaction, and every approval fails.
     * The job is idempotent anyway: a second run finds the tree unchanged and
     * writes nothing, which is all the lock was saving.
     *
     * The slug travels with the ULID because a purged tool has no row left to
     * read it from, and the directory still has to go.
     */
    public function __construct(public string $ulid, public string $slug)
    {
        $this->afterCommit = true;
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
