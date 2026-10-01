<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\RuntimeDirectories;
use Closure;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Deployment tasks as plain URLs, for hosting without SSH access.
 *
 * These URLs need no login and no token, so they are limited to tasks that
 * are safe for anyone to trigger: every one is non-destructive and can be
 * repeated without changing the result. Nothing here drops, wipes or resets
 * data, and seeding is refused once the database has users. Turn them off
 * with DEPLOY_ROUTES_ENABLED=false when they are not needed.
 */
class DeployController extends Controller
{
    /** Seconds that must pass between two deploy tasks. */
    private const int COOLDOWN = 5;

    /**
     * List the available deploy URLs.
     */
    public function index(): Response
    {
        $base = url('deploy');

        return $this->text(implode("\n", [
            'Deploy tasks',
            '============',
            '',
            "{$base}/run            Everything needed after an upload: folders, storage link, migrations, cache refresh",
            "{$base}/setup          Create missing storage and cache folders, and the storage link",
            "{$base}/migrate        Run pending database migrations",
            "{$base}/seed           Seed the database (only while it has no users)",
            "{$base}/storage-link   Link public/storage to storage/app/public",
            "{$base}/cache          Cache config, routes and views for speed",
            "{$base}/clear          Clear the config, route, view and application caches",
            '',
            'Each task is safe to run more than once. Set DEPLOY_ROUTES_ENABLED=false in .env to switch these URLs off.',
        ]));
    }

    public function setup(): Response
    {
        return $this->run('Setup', [
            'Runtime folders' => fn (): string => $this->ensureDirectories(),
            'Storage link' => fn (): string => $this->artisan('storage:link', ['--force' => true]),
        ]);
    }

    public function migrate(): Response
    {
        return $this->run('Migrate', [
            'Runtime folders' => fn (): string => $this->ensureDirectories(),
            'Migrations' => fn (): string => $this->artisan('migrate', ['--force' => true]),
        ]);
    }

    /**
     * Seed a fresh installation. Refused once any user exists, so an open URL
     * can never add accounts to, or overwrite, a database that is in use.
     */
    public function seed(): Response
    {
        if (! Schema::hasTable('users')) {
            return $this->text("Seed\n====\n\nThe database has no tables yet. Open ".url('deploy/migrate').' first.', 409);
        }

        if (User::query()->exists()) {
            return $this->text("Seed\n====\n\nSkipped: the database already has users, so nothing was seeded.", 409);
        }

        return $this->run('Seed', [
            'Seeders' => fn (): string => $this->artisan('db:seed', ['--force' => true]),
        ]);
    }

    public function storageLink(): Response
    {
        return $this->run('Storage link', [
            'Runtime folders' => fn (): string => $this->ensureDirectories(),
            'Storage link' => fn (): string => $this->artisan('storage:link', ['--force' => true]),
        ]);
    }

    public function cache(): Response
    {
        return $this->run('Cache', [
            'Optimize' => fn (): string => $this->artisan('optimize'),
        ]);
    }

    public function clear(): Response
    {
        return $this->run('Clear caches', [
            'Clear' => fn (): string => $this->artisan('optimize:clear'),
        ]);
    }

    /**
     * Everything a fresh upload needs, in order.
     */
    public function all(): Response
    {
        return $this->run('Deploy', [
            'Runtime folders' => fn (): string => $this->ensureDirectories(),
            'Clear caches' => fn (): string => $this->artisan('optimize:clear'),
            'Storage link' => fn (): string => $this->artisan('storage:link', ['--force' => true]),
            'Migrations' => fn (): string => $this->artisan('migrate', ['--force' => true]),
        ]);
    }

    /**
     * Run the steps one after another and report what each one printed.
     * A failing step stops the run; later steps are not attempted.
     *
     * @param  array<string, Closure(): string>  $steps
     */
    private function run(string $title, array $steps): Response
    {
        $lock = $this->acquireLock();

        if (is_string($lock)) {
            return $this->text("{$title}\n".str_repeat('=', mb_strlen($title))."\n\n{$lock}", 429);
        }

        @set_time_limit(300);

        $report = [$title, str_repeat('=', mb_strlen($title)), ''];
        $status = 200;

        try {
            foreach ($steps as $name => $step) {
                $report[] = "[{$name}]";

                try {
                    $report[] = trim($step()) ?: 'Done.';
                } catch (Throwable $exception) {
                    report($exception);

                    $report[] = 'FAILED: '.$exception->getMessage();
                    $report[] = '';
                    $report[] = 'Stopped here. The full error is in storage/logs/laravel.log.';
                    $status = 500;

                    break;
                }

                $report[] = '';
            }
        } finally {
            $this->releaseLock($lock);
        }

        if ($status === 200) {
            $report[] = 'Finished without errors.';
        }

        return $this->text(implode("\n", $report), $status);
    }

    private function ensureDirectories(): string
    {
        $created = RuntimeDirectories::ensure(base_path());

        return $created === []
            ? 'All runtime folders already exist.'
            : "Created:\n  ".implode("\n  ", $created);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function artisan(string $command, array $parameters = []): string
    {
        Artisan::call($command, $parameters);

        return Artisan::output();
    }

    /**
     * Allow one deploy task at a time, with a short pause between tasks. The
     * lock is a file rather than a cache entry because these URLs must work
     * before the database (and so the cache table) exists.
     *
     * @return resource|string the lock handle, or the reason it was refused
     */
    private function acquireLock(): mixed
    {
        RuntimeDirectories::ensure(base_path());

        $handle = @fopen(storage_path('framework/deploy.lock'), 'c+');

        if ($handle === false) {
            return 'The storage folder is not writable, so deploy tasks cannot run. Make storage/ writable by the web server.';
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return 'Another deploy task is still running. Try again when it has finished.';
        }

        $lastRun = (int) stream_get_contents($handle);

        if ($lastRun > 0 && time() - $lastRun < self::COOLDOWN) {
            flock($handle, LOCK_UN);
            fclose($handle);

            return 'A deploy task finished a moment ago. Wait a few seconds and try again.';
        }

        return $handle;
    }

    /**
     * @param  resource|string  $handle
     */
    private function releaseLock(mixed $handle): void
    {
        if (! is_resource($handle)) {
            return;
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string) time());
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    private function text(string $content, int $status = 200): Response
    {
        return response($content."\n", $status, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
