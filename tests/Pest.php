<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

// Use the base TestCase for Unit tests
uses(Tests\TestCase::class)->in('Unit/Api', 'Unit/Core', 'Unit/LMStudioFactoryStreamingTest.php', 'Unit/LMStudioFactoryTest.php', 'Unit/LMStudioFactoryIntegrationTest.php');

// Use Orchestra TestCase for Laravel-specific tests
uses(\Orchestra\Testbench\TestCase::class)->in('Unit/Laravel');

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
