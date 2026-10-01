<?php

namespace Tests\Unit\Support;

use App\Support\RuntimeDirectories;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

class RuntimeDirectoriesTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'hrms-runtime-'.bin2hex(random_bytes(6));
        mkdir($this->base);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->base);

        parent::tearDown();
    }

    public function test_creates_every_missing_runtime_folder(): void
    {
        $created = RuntimeDirectories::ensure($this->base);

        $this->assertSame(RuntimeDirectories::PATHS, $created);

        foreach (RuntimeDirectories::PATHS as $path) {
            $this->assertDirectoryExists($this->base.'/'.$path);
        }
    }

    public function test_leaves_existing_folders_and_their_contents_alone(): void
    {
        RuntimeDirectories::ensure($this->base);
        file_put_contents($this->base.'/storage/logs/laravel.log', 'kept');

        $created = RuntimeDirectories::ensure($this->base);

        $this->assertSame([], $created);
        $this->assertSame('kept', file_get_contents($this->base.'/storage/logs/laravel.log'));
    }

    public function test_creates_only_the_folders_that_are_missing(): void
    {
        mkdir($this->base.'/storage/logs', 0775, true);

        $created = RuntimeDirectories::ensure($this->base);

        $this->assertNotContains('storage/logs', $created);
        $this->assertContains('bootstrap/cache', $created);
    }
}
