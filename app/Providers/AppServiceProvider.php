<?php

namespace App\Providers;

use App\Models\User;
use App\Sandbox\BubblewrapSandboxRunner;
use App\Sandbox\DockerSandboxRunner;
use App\Sandbox\FakeSandboxRunner;
use App\Sandbox\NullSandboxRunner;
use App\Sandbox\SandboxRunner;
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
    }

    /**
     * `php artisan dev` runs one worker for every queue this box uses -
     * sandbox runs would otherwise sit in 待機中 forever - and Reverb for
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
