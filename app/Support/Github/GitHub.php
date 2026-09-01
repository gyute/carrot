<?php

namespace App\Support\Github;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
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
     * @return string|null the commit sha, or null when nothing differed
     */
    public function commit(array $write, array $remove, string $message): ?string
    {
        $branch = (string) config('github.branch');

        // Null on a repository with no commits yet - the one somebody just
        // created for this - and the first write starts its history.
        $head = $this->head($branch);
        $baseTree = $head === null ? null : (string) $this->get("git/commits/{$head}")['tree']['sha'];

        // Nothing to remove from a repository that holds nothing.
        if ($head === null && $write === []) {
            return null;
        }

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

        $tree = (string) $this->post('git/trees', array_filter([
            'base_tree' => $baseTree,
            'tree' => $entries,
        ], fn (mixed $value): bool => $value !== null))['sha'];

        // Nothing to say: the repository already holds exactly this.
        if ($tree === $baseTree) {
            return null;
        }

        $commit = (string) $this->post('git/commits', [
            'message' => $message,
            'tree' => $tree,
            // No parents makes it a root commit, which is what an empty
            // repository needs.
            'parents' => $head === null ? [] : [$head],
            'author' => $this->committer(),
            'committer' => $this->committer(),
        ])['sha'];

        if ($head === null) {
            $this->post('git/refs', ['ref' => "refs/heads/{$branch}", 'sha' => $commit]);

            return $commit;
        }

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
     * The commit the branch points at, or null when there is none: either the
     * repository has no commits yet, or GITHUB_BRANCH names a branch that
     * does not exist. Both end the same way - the branch is created.
     */
    private function head(string $branch): ?string
    {
        $response = $this->request()->get($this->url("git/ref/heads/{$branch}"));

        if ($response->status() === 404) {
            return null;
        }

        return (string) $response->throw()->json()['object']['sha'];
    }

    /**
     * @return array<string, mixed>
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

        if ($repository === '') {
            throw new RuntimeException('GITHUB_REPOSITORY is not set.');
        }

        return rtrim(config('github.api_url').'/repos/'.$repository.'/'.$path, '/');
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
