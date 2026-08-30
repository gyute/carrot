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
use App\Models\Message;
use App\Models\Tool;
use App\Models\ToolRequest;
use App\Models\ToolSubmission;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
 * The requests in the same file are seeded the same way, and one of the tools is
 * filed against one of them, so the demo also shows a request closing itself
 * when the tool that answers it goes live.
 */
class SeedDemo extends Command
{
    protected $signature = 'demo:seed
                            {--fresh : Delete the demo data and publish it again}
                            {--clear : Delete the demo data and stop, leaving everything else}
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

        if ($this->option('clear')) {
            $this->removeExisting($definition['tools'], $definition['requests'] ?? [], '--clear');
            $this->removeAccounts();
            $this->newLine();
            $this->components->info('デモのデータを削除しました。ほかのツールはそのままです。');

            return self::SUCCESS;
        }

        [$requester, $manager, $admin] = $this->accounts($department);

        if ($this->option('fresh')) {
            $this->removeExisting($definition['tools'], $definition['requests'] ?? [], '--fresh');
        }

        // Requests first: a tool may be filed against one, and approving it
        // then closes that request on its own.
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
     * Files each request as the demo requester and moves it to the state the
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
     * The request a tool entry answers, when it names one.
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
    private function removeExisting(array $tools, array $requests, string $label): void
    {
        $names = array_map(fn (array $entry): string => (string) $entry['name'], $tools);
        $titles = array_map(fn (array $entry): string => (string) $entry['title'], $requests);

        // What is about to go, collected before it goes, so the inbox and the
        // bell can be emptied of the announcements that pointed at it.
        $toolIds = Tool::withTrashed()->whereIn('name', $names)->pluck('id')->all();
        $submissions = ToolSubmission::query()
            ->whereIn('payload->name', $names)
            ->orWhereIn('tool_id', $toolIds)
            ->pluck('id')
            ->all();
        $requestIds = ToolRequest::query()->whereIn('title', $titles)->pluck('id')->all();

        $announced = $this->removeAnnouncements(ToolSubmission::class, $submissions)
            + $this->removeAnnouncements(ToolRequest::class, $requestIds);

        ToolRequest::query()->whereIn('id', $requestIds)->delete();

        $removed = 0;

        foreach (Tool::withTrashed()->whereIn('name', $names)->get() as $tool) {
            $tool->forceDelete();
            $removed++;
        }

        ToolSubmission::query()
            ->whereNull('tool_id')
            ->whereIn('payload->name', $names)
            ->delete();

        $this->components->twoColumnDetail($label, "<fg=gray>ツール {$removed} 件 / お知らせ {$announced} 件を削除しました</>");
    }

    /**
     * Deletes the inbox messages raised about `$ids`, and the bell rows that
     * link to them. Without this a demo published a few times leaves every
     * old announcement behind, since nothing else ties them to the rows they
     * were about.
     *
     * @param  class-string  $type
     * @param  array<int, int>  $ids
     */
    private function removeAnnouncements(string $type, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $messages = Message::query()->where('subject_type', $type)->whereIn('subject_id', $ids);
        $ulids = $messages->clone()->pluck('ulid')->all();
        $removed = $messages->delete();

        if ($ulids !== []) {
            // `notifications.data` is a text column, so match the ULID in the
            // JSON rather than reaching for a JSON path the driver may not have.
            DB::table('notifications')
                ->where(function ($query) use ($ulids): void {
                    foreach ($ulids as $ulid) {
                        $query->orWhere('data', 'like', '%"'.$ulid.'"%');
                    }
                })
                ->delete();
        }

        return $removed;
    }

    /**
     * The three demo accounts. Removing them is part of clearing the demo:
     * they carry a documented password, so they should not outlive the data
     * they were made for.
     */
    private function removeAccounts(): void
    {
        $accounts = User::query()->whereIn('username', ['demo', 'demo-manager', 'demo-admin'])->pluck('id');

        // A tool still pointing at one of their submissions blocks the
        // delete: the cascade empties tool_submissions while the tools row is
        // being updated, and the reference is re-checked mid-flight.
        Tool::withTrashed()
            ->whereIn('approved_submission_id', ToolSubmission::query()->whereIn('user_id', $accounts)->select('id'))
            ->update(['approved_submission_id' => null]);

        // Everything else the accounts touched is reached by a foreign key and
        // goes with them; the bell is not, so it would keep rows pointing at
        // messages that no longer exist.
        DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', $accounts)
            ->delete();

        // Force, because users are retired rather than deleted now, and a
        // retired row would hold the login IDs the next seed wants back.
        $removed = User::query()->whereIn('id', $accounts)->forceDelete();

        $this->components->twoColumnDetail('--clear', "<fg=gray>デモ用アカウント {$removed} 件を削除しました</>");
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
