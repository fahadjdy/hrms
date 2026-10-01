<?php

use App\Http\Middleware\EnsureDeployRoutesEnabled;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTenant;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\RuntimeDirectories;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

// An FTP upload can leave out the empty storage and cache folders. Laravel
// cannot boot without them, so they are created here, before anything else.
RuntimeDirectories::ensure(dirname(__DIR__));

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Deploy URLs sit outside the "web" group: they must work before the
            // database exists, so they cannot depend on sessions or the cache.
            Route::prefix('deploy')
                ->name('deploy.')
                ->middleware(EnsureDeployRoutesEnabled::class)
                ->group(__DIR__.'/../routes/deploy.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'tenant' => EnsureTenant::class,
            'super-admin' => EnsureSuperAdmin::class,
        ]);

        // The tenant must be known before route model binding runs, so that
        // bound models are resolved through the company scope and another
        // company's record is a 404.
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
