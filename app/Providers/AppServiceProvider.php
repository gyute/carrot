<?php

namespace App\Providers;

use App\Jobs\MirrorToolToRepo;
use App\Jobs\SyncSubmissionPullRequest;
use App\Models\Tool;
use App\Models\ToolSubmission;
use App\Models\User;
use App\Sandbox\BubblewrapSandboxRunner;
use App\Sandbox\DockerSandboxRunner;
use App\Sandbox\FakeSandboxRunner;
use App\Sandbox\NullSandboxRunner;
use App\Sandbox\SandboxRunner;
use App\Support\Github\GitHub;
use App\Support\SystemStatus;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SandboxRunner::class, fn (): SandboxRunner => match (config('sandbox.driver')) {
            'docker' => new DockerSandboxRunner(
                binary: (string) config('sandbox.binary'),
                images: config('sandbox.images'),
                workdirBase: (string) config('sandbox.workdir'),
                outputBytes: (int) config('sandbox.output_bytes'),
                cpus: (string) config('sandbox.cpus'),
                pids: (int) config('sandbox.pids'),
                requireRootless: (bool) config('sandbox.require_rootless'),
            ),
            'bubblewrap' => new BubblewrapSandboxRunner(
                workdirBase: (string) config('sandbox.workdir'),
                outputBytes: (int) config('sandbox.output_bytes'),
            ),
            'fake' => new FakeSandboxRunner,
            default => new NullSandboxRunner,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureDevProcesses();

        Gate::define('admin', fn (User $user): bool => $user->isAdmin());
        Gate::define('reviewer', fn (User $user): bool => $user->isReviewer());

        // Each worker loop leaves a pulse the admin system screen reads.
        Event::listen(Looping::class, fn (Looping $event) => SystemStatus::heartbeat((string) $event->queue));

        RateLimiter::for('tool-runs', fn (Request $request): Limit => Limit::perMinute((int) config('sandbox.rate_limit_per_minute'))
            ->by((string) $request->user()?->id));

        $this->mirrorToolChanges();
    }

    /**
     * Every way a tool can change, watched in one place.
     *
     * Hooking the eight code paths that write to `tools` one by one would be
     * a list to keep up to date - and it already fell behind once, when
     * retiring a person started handing tools to a successor. The row itself
     * is the thing to watch.
     *
     * The flag is read per event rather than here, so a deployment that turns
     * the mirror on does not need a restart, and tests can toggle it.
     */
    protected function mirrorToolChanges(): void
    {
        $mirror = function (Tool $tool): void {
            MirrorToolToRepo::dispatchIf(GitHub::enabled(), $tool->ulid, $tool->slug);
        };

        Tool::saved($mirror);
        Tool::deleted($mirror);
        Tool::restored($mirror);
        Tool::forceDeleted($mirror);

        // Same reasoning for the review side: withdrawing a submission raises
        // no event at all, and the statuses are set from five places.
        ToolSubmission::saved(function (ToolSubmission $submission): void {
            SyncSubmissionPullRequest::dispatchIf(GitHub::enabled(), $submission->ulid);
        });
    }

    /**
     * `php artisan dev` runs one worker for every queue this box uses -
     * sandbox runs would otherwise sit queued forever - and Reverb for
     * live updates.
     */
    protected function configureDevProcesses(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        DevCommands::except('queue');
        DevCommands::artisan('queue:listen --queue=sandbox,default --tries=1 --timeout=0', 'worker');
        DevCommands::artisan('reverb:start', 'reverb');
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
