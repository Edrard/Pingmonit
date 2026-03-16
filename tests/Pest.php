<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific
| PHPUnit test case class. By default, that class is "PHPUnit\Framework\TestCase".
| Of course, you may need to change it using the "uses()" function to bind a
| different classes or traits.
|
*/

// Commented out TestCase usage to allow simple tests to work
// uses(Tests\TestCase::class)->in('Unit');
// uses(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions.
| The "expect()" function is a great way to do that. By default, we're using
| the "Pest\Expectation" class, which provides a set of matching methods.
|
*/

expect()->extend('toBeOneOf', function (array $allowed) {
    return $this->toBeIn($allowed);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out of the box, you may have some testing code specific
| to your project that you don't want to repeat in every file. Here you can also
| expose helpers as global functions to help you to reduce the number of lines
| in your test files.
|
*/

function createMockState(array $data = []): array
{
    return array_merge([
        'ip' => '192.168.1.100',
        'status' => 'good',
        'failures' => 0,
        'last_test_at' => '2026-03-06T12:00:00+00:00'
    ], $data);
}

function createMockUpsConfig(array $overrides = []): array
{
    return array_merge([
        'ip' => '192.168.1.100',
        'name' => 'Test UPS',
        'send_email' => true,
        'send_telegram' => true,
        'oid_capacity' => '1.3.6.1.4.1.318.1.1.1.2.2.1.0',
        'oid_runtime' => '1.3.6.1.4.1.318.1.1.1.2.3.1.0',
        'oid_time_on_battery' => '1.3.6.1.4.1.318.1.1.1.2.1.1.0',
        'oid_battery_status' => '1.3.6.1.4.1.318.1.1.1.2.1.2.0',
        'thresholds' => [
            'warning' => 90,
            'critical' => 50
        ]
    ], $overrides);
}

function createMockHostConfig(array $overrides = []): array
{
    return array_merge([
        'ip' => '8.8.8.8',
        'name' => 'Google DNS',
        'send_email' => true,
        'send_telegram' => true
    ], $overrides);
}

function createTempFile(string $filename): string
{
    $tempDir = __DIR__ . '/../temp';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    return $tempDir . '/' . $filename;
}

function cleanupTempFile(string $filename): void
{
    $file = __DIR__ . '/../temp/' . $filename;
    if (file_exists($file)) {
        unlink($file);
    }
}
