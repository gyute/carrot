<?php

namespace App\Console\Commands;

use App\Actions\Tools\ApproveSubmission;
use App\Actions\Tools\EndorseSubmission;
use App\Enums\SubmissionAction;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\Tool;
use App\Models\ToolSubmission;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Publishes the demo catalog described by demo/tools.php.
 *
 * It goes through the real flow rather than writing `tools` rows directly:
 * the demo requester files a submission, their department manager endorses
 * it and an admin publishes it. So the demo is also a demonstration - the
 * approval history, the versions and the inbox messages are all genuine.
 */
class SeedDemo extends Command
{
    protected $signature = 'demo:seed
                            {--fresh : Delete the demo tools and start over}
                            {--force : Run even when the app is in production}';

    protected $description = 'Publish the demo catalog from demo/tools.php';

    public function handle(EndorseSubmission $endorse, ApproveSubmission $approve): int
    {
        if ($this->getLaravel()->isProduction() && ! $this->option('force')) {
            $this->components->error('Refusing to seed demo data in production. Pass --force if you mean it.');

            return self::FAILURE;
        }

        $definition = $this->definition();
        $department = (string) $definition['department'];
        [$requester, $manager, $admin] = $this->accounts($department);

        if ($this->option('fresh')) {
            $this->removeExisting($definition['tools']);
        }

        foreach ($definition['tools'] as $entry) {
            $name = (string) $entry['name'];

            if (Tool::withTrashed()->where('name', $name)->exists()) {
                $this->components->twoColumnDetail($name, '<fg=gray>すでにあります</>');

                continue;
            }

            $submission = ToolSubmission::query()->create([
                'user_id' => $requester->id,
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
     * @return array{department: string, tools: array<int, array<string, mixed>>}
     */
    private function definition(): array
    {
        $path = base_path('demo/tools.php');

        if (! File::exists($path)) {
            throw new RuntimeException("demo/tools.php not found at {$path}.");
        }

        /** @var array{department: string, tools: array<int, array<string, mixed>>} $definition */
        $definition = require $path;

        return $definition;
    }

    /**
     * The three people a demo needs: someone to ask, someone to endorse and
     * someone to publish. Created if they are not there yet, left alone if
     * they are.
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
     */
    private function removeExisting(array $tools): void
    {
        $names = array_map(fn (array $entry): string => (string) $entry['name'], $tools);

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
