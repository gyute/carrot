<?php

namespace App\Console\Commands;

use App\Actions\Tools\ApproveSubmission;
use App\Actions\Tools\EndorseSubmission;
use App\Actions\Tools\TriageToolRequest;
use App\Enums\SubmissionAction;
use App\Enums\SubmissionStatus;
use App\Enums\ToolKind;
use App\Enums\ToolRequestPriority;
use App\Enums\ToolRequestStatus;
use App\Enums\UserRole;
use App\Models\Tool;
use App\Models\ToolRequest;
use App\Models\ToolSubmission;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Publishes the demo catalog described by demo/tools.php.
 *
 * It goes through the real flow rather than writing `tools` rows directly:
 * the demo requester files a submission, their department manager endorses
 * it and an admin publishes it. So the demo is also a demonstration - the
 * approval history, the versions and the inbox messages are all genuine.
 *
 * The asks in the same file are seeded the same way, and one of the tools is
 * filed against one of them, so the demo also shows a request closing itself
 * when the tool that answers it goes live.
 */
class SeedDemo extends Command
{
    protected $signature = 'demo:seed
                            {--fresh : Delete the demo tools and start over}
                            {--force : Run even when the app is in production}';

    protected $description = 'Publish the demo catalog from demo/tools.php';

    public function handle(EndorseSubmission $endorse, ApproveSubmission $approve, TriageToolRequest $triage): int
    {
        if ($this->getLaravel()->isProduction() && ! $this->option('force')) {
            $this->components->error('Refusing to seed demo data in production. Pass --force if you mean it.');

            return self::FAILURE;
        }

        $definition = $this->definition();
        $department = (string) $definition['department'];
        [$requester, $manager, $admin] = $this->accounts($department);

        if ($this->option('fresh')) {
            $this->removeExisting($definition['tools'], $definition['requests'] ?? []);
        }

        // Asks first: a tool may be filed against one, and approving it then
        // closes that ask on its own.
        $this->seedRequests($definition['requests'] ?? [], $requester, $admin, $triage);

        foreach ($definition['tools'] as $entry) {
            $name = (string) $entry['name'];

            if (Tool::withTrashed()->where('name', $name)->exists()) {
                $this->components->twoColumnDetail($name, '<fg=gray>すでにあります</>');

                continue;
            }

            $submission = ToolSubmission::query()->create([
                'user_id' => $requester->id,
                'tool_request_id' => $this->answered($entry)?->id,
                'action' => SubmissionAction::Create,
                'status' => SubmissionStatus::Pending,
                'payload' => $this->payload($entry, $department),
                'note' => 'デモ用の申請です。',
                'submitted_at' => now(),
            ]);

            if (($entry['state'] ?? 'published') !== 'published') {
                $this->components->twoColumnDetail($name, '<fg=yellow>承認待ち</>');

                continue;
            }

            $endorse->handle($submission, $manager, 'デモとして承認しました。');
            $tool = $approve->handle($submission, $admin, 'デモとして公開しました。');

            $this->components->twoColumnDetail($name, "<fg=green>公開 v{$tool->version}</>");
        }

        $this->newLine();
        $this->components->info("デモの申請者は {$requester->username} / 部署管理者は {$manager->username} / 管理者は {$admin->username}（パスワードは password）です。");
        $this->components->warn('お知らせと受信箱はキュー経由です。`composer run dev` かワーカーを動かすと届きます。');

        return self::SUCCESS;
    }

    /**
     * @return array{department: string, tools: array<int, array<string, mixed>>, requests?: array<int, array<string, mixed>>}
     */
    private function definition(): array
    {
        $path = base_path('demo/tools.php');

        if (! File::exists($path)) {
            throw new RuntimeException("demo/tools.php not found at {$path}.");
        }

        /** @var array{department: string, tools: array<int, array<string, mixed>>, requests?: array<int, array<string, mixed>>} $definition */
        $definition = require $path;

        return $definition;
    }

    /**
     * Files each ask as the demo requester and moves it to the state the
     * definition asks for, through the same action the triage screen uses.
     *
     * @param  array<int, array<string, mixed>>  $requests
     */
    private function seedRequests(array $requests, User $requester, User $admin, TriageToolRequest $triage): void
    {
        foreach ($requests as $entry) {
            $title = (string) $entry['title'];

            if (ToolRequest::query()->where('title', $title)->exists()) {
                $this->components->twoColumnDetail($title, '<fg=gray>すでにあります</>');

                continue;
            }

            $neededBy = $entry['needed_by'] ?? null;

            $toolRequest = ToolRequest::query()->create([
                'user_id' => $requester->id,
                'status' => ToolRequestStatus::Open,
                'title' => $title,
                'body' => (string) $entry['body'],
                'department' => $requester->department,
                'categories' => $entry['categories'] ?? [],
                'desired_kind' => ToolKind::tryFrom((string) ($entry['desired_kind'] ?? '')),
                // Relative, so a demo box seeded months ago is not overdue.
                'needed_by' => is_string($neededBy) ? Carbon::parse($neededBy) : null,
            ]);

            $state = (string) ($entry['state'] ?? 'open');

            if ($state === 'accepted' || $state === 'in_progress') {
                $triage->accept($toolRequest, $admin, 'デモとして受け付けました。', ToolRequestPriority::Normal);
            }

            if ($state === 'in_progress') {
                $triage->start($toolRequest, $admin, 'デモとして着手しました。');
            }

            $this->components->twoColumnDetail($title, "<fg=cyan>{$toolRequest->status->label()}</>");
        }
    }

    /**
     * The ask a tool entry answers, when it names one.
     *
     * @param  array<string, mixed>  $entry
     */
    private function answered(array $entry): ?ToolRequest
    {
        $title = $entry['answers'] ?? null;

        return is_string($title)
            ? ToolRequest::query()->where('title', $title)->first()
            : null;
    }

    /**
     * The three people a demo needs: someone to ask, someone to endorse and
     * someone to publish. Reset on every run, so a demo box is never one
     * forgotten password away from being unusable.
     *
     * @return array{0: User, 1: User, 2: User}
     */
    private function accounts(string $department): array
    {
        $make = fn (string $username, string $name, UserRole $role, ?string $dept): User => User::query()->updateOrCreate(
            ['username' => $username],
            [
                'name' => $name,
                'email' => "{$username}@example.com",
                'password' => 'password',
                'role' => $role,
                'department' => $dept,
                'email_verified_at' => now(),
            ],
        );

        return [
            $make('demo', 'デモ申請者', UserRole::Member, $department),
            $make('demo-manager', 'デモ部署管理者', UserRole::Manager, $department),
            $make('demo-admin', 'デモシステム管理者', UserRole::Admin, null),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $tools
     * @param  array<int, array<string, mixed>>  $requests
     */
    private function removeExisting(array $tools, array $requests): void
    {
        $names = array_map(fn (array $entry): string => (string) $entry['name'], $tools);

        ToolRequest::query()
            ->whereIn('title', array_map(fn (array $entry): string => (string) $entry['title'], $requests))
            ->delete();

        $removed = 0;

        foreach (Tool::withTrashed()->whereIn('name', $names)->get() as $tool) {
            $tool->forceDelete();
            $removed++;
        }

        ToolSubmission::query()
            ->whereNull('tool_id')
            ->whereIn('payload->name', $names)
            ->delete();

        $this->components->twoColumnDetail('--fresh', "<fg=gray>{$removed} 件を削除しました</>");
    }

    /**
     * The entry as a submission payload, with the script read off disk so the
     * source of a demo tool is an editable file rather than a string in an
     * array.
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function payload(array $entry, string $department): array
    {
        $source = null;

        if (isset($entry['source'])) {
            $path = base_path('demo/'.$entry['source']);

            if (! File::exists($path)) {
                throw new RuntimeException("Demo script {$entry['source']} not found at {$path}.");
            }

            $source = File::get($path);
        }

        return [
            'kind' => $entry['kind'],
            'name' => $entry['name'],
            'summary' => $entry['summary'],
            'description' => $entry['description'] ?? null,
            'icon' => $entry['icon'] ?? 'wrench',
            'accent' => $entry['accent'] ?? 'slate',
            'department' => $department,
            'categories' => $entry['categories'] ?? [],
            'config' => $entry['config'] ?? [],
            'source' => $source,
        ];
    }
}
