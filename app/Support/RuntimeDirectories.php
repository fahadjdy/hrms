<?php

namespace App\Support;

/**
 * The folders Laravel writes to at runtime. They hold no tracked files, so an
 * FTP deployment can leave them out; without them the application cannot
 * boot. They are created when missing, both at boot and by the deploy URLs.
 */
class RuntimeDirectories
{
    public const array PATHS = [
        'bootstrap/cache',
        'storage/app/private',
        'storage/app/public',
        'storage/framework/cache/data',
        'storage/framework/sessions',
        'storage/framework/testing',
        'storage/framework/views',
        'storage/logs',
    ];

    /**
     * Create every missing runtime folder under the given base path.
     *
     * @return list<string> the folders that had to be created
     */
    public static function ensure(string $basePath): array
    {
        $created = [];

        foreach (self::PATHS as $path) {
            $directory = $basePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);

            if (is_dir($directory)) {
                continue;
            }

            // Another request may create the folder between the check and the call.
            if (@mkdir($directory, 0775, true) || is_dir($directory)) {
                $created[] = $path;
            }
        }

        return $created;
    }
}
