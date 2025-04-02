<?php

declare(strict_types=1);

// Explicitly include the Composer autoloader
require_once __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide here will be used as the base test case for
| all your application tests. This will be used most of the time unless
| you decide to specify another base test case class manually.
|
*/

uses(Tests\TestCase::class)
    // REMOVE Laravel directory constraint
    ->in('Unit'); // Scan only the 'Unit' directory (excluding the removed Laravel subdir)

/*
|--------------------------------------------------------------------------
| Mock Helper
|--------------------------------------------------------------------------
|
| This function provides a convenient way to load mock files from a central location.
| It handles path resolution and optionally decodes JSON content.
|
*/

/**
 * Load a mock file from the mocks directory.
 *
 * @param  string  $path  Relative path from the mocks directory (e.g., 'chat/standard-response.json')
 * @param  bool  $decode  Whether to decode the JSON content (default: true)
 * @param  bool  $associative  Whether to decode as associative array (default: true)
 * @return mixed The file contents or decoded JSON
 *
 * @throws \RuntimeException If the file cannot be found
 */
function load_mock(string $path, bool $decode = true, bool $associative = true)
{
    $mockPath = __DIR__.'/mocks/'.$path;

    if (! file_exists($mockPath)) {
        throw new \RuntimeException("Mock file not found: {$mockPath}");
    }

    $contents = file_get_contents($mockPath);

    if ($contents === false) {
        throw new \RuntimeException("Failed to read mock file: {$mockPath}");
    }

    return $decode ? json_decode($contents, $associative) : $contents;
}
