<?php

namespace App\Jobs;

use App\Enums\SubmissionStatus;
use App\Models\ToolSubmission;
use App\Support\Github\GitHub;
use App\Support\Github\SubmissionDocument;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Keeps a submission's pull request in step with the submission.
 *
 * State, not events, for the same reason the tool mirror is: withdrawing a
 * submission raises no event at all, and the statuses are set from five
 * different places. The job reads the row and makes GitHub match it.
 *
 * The portal decides; GitHub records. A merge that will not go through is not
 * an error - the approval already happened, and MirrorToolToRepo writes the
 * published state to the branch regardless.
 */
class SyncSubmissionPullRequest implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public string $ulid) {}

    public function uniqueId(): string
    {
        return $this->ulid;
    }

    /**
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

        $submission = ToolSubmission::query()
            ->with(['tool.tags', 'tool.owner', 'user', 'reviewer', 'endorser', 'toolRequest'])
            ->where('ulid', $this->ulid)
            ->first();

        if ($submission === null) {
            return;
        }

        match ($submission->status) {
            SubmissionStatus::Draft => null,
            SubmissionStatus::Pending, SubmissionStatus::Endorsed => $this->propose($github, $submission),
            SubmissionStatus::Approved => $this->accept($github, $submission),
            SubmissionStatus::Rejected, SubmissionStatus::Withdrawn => $this->abandon($github, $submission),
        };
    }

    /**
     * Put the change up for review: a branch of its own, and a pull request
     * describing what it would do.
     */
    private function propose(GitHub $github, ToolSubmission $submission): void
    {
        $document = new SubmissionDocument($submission);
        $branch = $document->branch();

        $github->branchFrom($branch);
        $github->commit($document->files(), [], $document->title()."\n\n".$document->body(), $branch);

        $number = $github->openPullRequest($branch, $document->title(), $document->body());

        if ($submission->github_pr_number !== $number) {
            $submission->forceFill(['github_pr_number' => $number])->saveQuietly();
        }
    }

    private function accept(GitHub $github, ToolSubmission $submission): void
    {
        if ($submission->github_pr_number === null) {
            return;
        }

        $document = new SubmissionDocument($submission);
        $sha = $github->mergePullRequest($submission->github_pr_number, $document->title());

        if ($sha !== null && $submission->tool !== null) {
            $submission->tool->forceFill(['mirror_commit_sha' => $sha])->saveQuietly();
        }

        // A merge that did not go through leaves the branch behind on purpose:
        // somebody will want to see why.
        if ($sha !== null) {
            $github->deleteBranch($document->branch());
        }
    }

    private function abandon(GitHub $github, ToolSubmission $submission): void
    {
        if ($submission->github_pr_number === null) {
            return;
        }

        $github->closePullRequest($submission->github_pr_number);
        $github->deleteBranch((new SubmissionDocument($submission))->branch());
    }
}
