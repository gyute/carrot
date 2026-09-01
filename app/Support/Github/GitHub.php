<?php

namespace App\Support\Github;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The slice of the Git Data API the mirror needs: write a set of files as one
 * commit on one branch.
 *
 * The Contents API would be shorter, but it commits per file, so a tool whose
 * config and source both changed would land as two commits with a broken
 * state in between. Building the tree by hand keeps a change to a tool a
 * single commit.
 */
class GitHub
{
    public static function enabled(): bool
    {
        return is_string(config('github.repository')) && config('github.repository') !== ''
            && is_string(config('github.token')) && config('github.token') !== '';
    }

    /**
     * Writes and removes paths in one commit on the configured branch.
     *
     * @param  array<string, string>  $write  path => contents
     * @param  array<int, string>  $remove  directories to drop
     * @param  string|null  $branch  defaults to the configured one
     * @return string|null the commit sha, or null when nothing differed
     */
    public function commit(array $write, array $remove, string $message, ?string $branch = null): ?string
    {
        $branch ??= (string) config('github.branch');

        $head = $this->head($branch);

        // Nothing to remove from a repository that holds nothing.
        if ($head === null && $write === []) {
            return null;
        }

        $head ??= $this->start($branch);
        $baseTree = (string) $this->get("git/commits/{$head}")['tree']['sha'];

        $entries = [];

        foreach ($write as $path => $contents) {
            $entries[] = [
                'path' => $path,
                'mode' => '100644',
                'type' => 'blob',
                'sha' => (string) $this->post('git/blobs', [
                    'content' => base64_encode($contents),
                    'encoding' => 'base64',
                ])['sha'],
            ];
        }

        foreach ($remove as $path) {
            // A null sha on a tree entry drops the whole directory.
            $entries[] = ['path' => $path, 'mode' => '040000', 'type' => 'tree', 'sha' => null];
        }

        if ($entries === []) {
            return null;
        }

        $tree = (string) $this->post('git/trees', ['base_tree' => $baseTree, 'tree' => $entries])['sha'];

        // Nothing to say: the repository already holds exactly this.
        if ($tree === $baseTree) {
            return null;
        }

        $commit = (string) $this->post('git/commits', [
            'message' => $message,
            'tree' => $tree,
            'parents' => [$head],
            'author' => $this->committer(),
            'committer' => $this->committer(),
        ])['sha'];

        // Not forced: if the branch moved while this was being built the call
        // fails and the job runs again against the new head.
        $this->patch("git/refs/heads/{$branch}", ['sha' => $commit]);

        return $commit;
    }

    /**
     * What an operator needs to know before trusting the mirror: the token
     * reaches the repository, may push to it, and the repository is private.
     * Mirroring internal tooling to a public repository would publish it.
     *
     * @return array{ok: bool, message: string|null}
     */
    public function check(): array
    {
        $repository = (string) config('github.repository');

        // A name without an owner reaches GitHub as a 404, which reads like a
        // missing repository or a bad token rather than the typo it is.
        if (! preg_match('#^[^/\s]+/[^/\s]+$#', $repository)) {
            return ['ok' => false, 'message' => "GITHUB_REPOSITORY は owner/name の形式で指定してください（現在: {$repository}）。"];
        }

        try {
            $repo = $this->request()->get($this->url(''))->throw()->json();
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => "{$repository} に到達できません: {$e->getMessage()}"];
        }

        if (($repo['private'] ?? false) !== true) {
            return ['ok' => false, 'message' => "{$repository} は公開リポジトリです。社内ツールの内容が公開されます。"];
        }

        if (($repo['permissions']['push'] ?? false) !== true) {
            return ['ok' => false, 'message' => "{$repository} への書き込み権限がトークンにありません。"];
        }

        // Says so here rather than leaving it for the first tool somebody
        // changes, or the first submission somebody files, to fail on.
        try {
            $this->head((string) config('github.branch'));
            $this->pulls('pulls?state=open&per_page=1');
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        return ['ok' => true, 'message' => null];
    }

    /**
     * @return array<string, string>
     */
    private function committer(): array
    {
        return [
            'name' => (string) config('github.committer.name'),
            'email' => (string) config('github.committer.email'),
        ];
    }

    /**
     * Points `$branch` at the tip of the configured one, creating it if it is
     * not there yet. A branch that already exists is left where it is: the
     * submission it belongs to keeps its own history.
     */
    public function branchFrom(string $branch): void
    {
        if ($this->request()->get($this->url("git/ref/heads/{$branch}"))->status() !== 404) {
            return;
        }

        $this->post('git/refs', [
            'ref' => "refs/heads/{$branch}",
            'sha' => $this->head((string) config('github.branch')) ?? $this->start((string) config('github.branch')),
        ]);
    }

    /**
     * Opens a pull request for `$branch`, or returns the number of the one
     * that is already open for it.
     */
    public function openPullRequest(string $branch, string $title, string $body): int
    {
        $open = $this->pulls('pulls?state=open&head='.rawurlencode($this->owner().':'.$branch));

        if ($open !== []) {
            $number = (int) $open[0]['number'];

            // The submission is the source of truth, so a pull request that
            // has drifted from it is brought back rather than left as it was.
            if ($open[0]['title'] !== $title || $open[0]['body'] !== $body) {
                $this->request()->patch($this->url("pulls/{$number}"), ['title' => $title, 'body' => $body])->throw();
            }

            return $number;
        }

        $response = $this->request()->post($this->url('pulls'), [
            'title' => $title,
            'body' => $body,
            'head' => $branch,
            'base' => (string) config('github.branch'),
        ]);

        $this->refusedForScope($response);

        return (int) $response->throw()->json()['number'];
    }

    /**
     * Squashes the pull request onto the branch. Null when GitHub refuses -
     * a conflict, or nothing left to merge - which is not an error here: the
     * portal has already decided, and the mirror writes the result anyway.
     */
    public function mergePullRequest(int $number, string $title): ?string
    {
        $response = $this->request()->put($this->url("pulls/{$number}/merge"), [
            'merge_method' => 'squash',
            'commit_title' => $title,
        ]);

        $this->refusedForScope($response);

        return $response->successful() ? (string) $response->json()['sha'] : null;
    }

    public function closePullRequest(int $number): void
    {
        $response = $this->request()->patch($this->url("pulls/{$number}"), ['state' => 'closed']);

        $this->refusedForScope($response);
        $response->throw();
    }

    /**
     * A 403 here is not "no such repository" - it is a token that may write
     * files but not open pull requests, which the raw message does not say.
     */
    private function refusedForScope(Response $response): void
    {
        if ($response->status() === 403) {
            throw new RuntimeException(
                'GitHub: the token cannot work with pull requests. Add the "Pull requests: read and write" permission to it - "Contents" alone only covers the mirror.',
            );
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private function pulls(string $path): array
    {
        $response = $this->request()->get($this->url($path));

        $this->refusedForScope($response);

        return $response->throw()->json();
    }

    public function deleteBranch(string $branch): void
    {
        $this->request()->delete($this->url("git/refs/heads/{$branch}"));
    }

    private function owner(): string
    {
        return Str::before((string) config('github.repository'), '/');
    }

    /**
     * Gives an empty repository its first commit, and with it the branch.
     *
     * The Git Data API cannot do this: with no commits behind it, even
     * creating a blob comes back 409. The Contents API can, and creates the
     * branch on the way, so the mirror only needs it this once.
     */
    private function start(string $branch): string
    {
        $this->request()->put($this->url('contents/README.md'), [
            'message' => "Start the tool mirror\n",
            'content' => base64_encode("# Tool mirror\n\nOne directory per published tool, written by CARROT.\n"),
            'branch' => $branch,
            'committer' => $this->committer(),
        ])->throw();

        return $this->head($branch)
            ?? throw new RuntimeException('GitHub: the repository is still empty after starting it.');
    }

    /**
     * The commit the branch points at, or null when the repository is empty
     * and the first write has to start its history.
     */
    private function head(string $branch): ?string
    {
        $response = $this->request()->get($this->url("git/ref/heads/{$branch}"));

        // 409 is GitHub saying the repository holds nothing at all, which no
        // typo can produce - so there is no history to orphan and the first
        // write starts one.
        if ($response->status() === 409) {
            return null;
        }

        // 404 is a branch that is not there in a repository that has others:
        // a typo in GITHUB_BRANCH, or a default branch called something else.
        // Starting a branch here would bury the mistake somewhere nobody looks.
        if ($response->status() === 404) {
            throw new RuntimeException(
                "GitHub: the repository has no branch named `{$branch}`. Point GITHUB_BRANCH at one that exists.",
            );
        }

        return (string) $response->throw()->json()['object']['sha'];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function get(string $path): array
    {
        return $this->request()->get($this->url($path))->throw()->json();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(string $path, array $body): array
    {
        return $this->request()->post($this->url($path), $body)->throw()->json();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function patch(string $path, array $body): array
    {
        return $this->request()->patch($this->url($path), $body)->throw()->json();
    }

    private function url(string $path): string
    {
        $repository = (string) config('github.repository');

        if (! preg_match('#^[^/\s]+/[^/\s]+$#', $repository)) {
            throw new RuntimeException("GitHub: GITHUB_REPOSITORY has to be owner/name, not `{$repository}`.");
        }

        $base = config('github.api_url').'/repos/'.$repository;

        // The trailing slash in `git/matching-refs/heads/` is what makes it
        // mean "every branch", so it is not trimmed away.
        return $path === '' ? $base : $base.'/'.ltrim($path, '/');
    }

    private function request(): PendingRequest
    {
        return Http::withToken((string) config('github.token'))
            ->timeout((int) config('github.timeout', 15))
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ]);
    }
}
