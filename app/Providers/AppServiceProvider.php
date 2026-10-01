<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\User;
use App\Services\WorkingCalendarService;
use App\Services\WorkingHoursCalculationService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped: one instance per request or queued job, so tenant state and
        // per-company caches never carry over between them.
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(WorkingCalendarService::class);
        $this->app->scoped(WorkingHoursCalculationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureModels();
        $this->configurePermissions();
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

    /**
     * Surface N+1 queries outside production: tests fail on them, and local
     * development logs them instead of breaking the page.
     */
    protected function configureModels(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        if (! app()->runningUnitTests()) {
            Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
                Log::warning('Lazy loading violation: '.$model::class.'::'.$relation);
            });
        }
    }

    /**
     * Every permission is a gate, so routes can use the `can:` middleware.
     */
    protected function configurePermissions(): void
    {
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user): bool => $user->hasPermission($permission));
        }
    }
}
