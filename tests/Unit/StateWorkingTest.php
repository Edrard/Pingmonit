<?php

namespace Tests\Unit;

use Edrard\Pingmonit\State\HostStateRepository;
use Edrard\Pingmonit\State\JsonStateStore;

test('State repository stores and retrieves data', function () {
    $tempFile = __DIR__ . '/../temp/test_state.json';
    $stateStore = new JsonStateStore($tempFile);
    $state = new HostStateRepository($stateStore);
    
    $testData = [
        'ip' => '192.168.1.100',
        'status' => 'good',
        'failures' => 0,
        'capacity' => 95
    ];
    
    $state->set('192.168.1.100', $testData);
    $retrieved = $state->get('192.168.1.100');
    
    expect($retrieved)->toBe($testData);
    
    // Clean up
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
});

test('State repository returns null for missing IP', function () {
    $tempFile = __DIR__ . '/../temp/test_state.json';
    $stateStore = new JsonStateStore($tempFile);
    $state = new HostStateRepository($stateStore);
    
    $result = $state->get('192.168.1.999');
    expect($result)->toBeNull();
    
    // Clean up
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
});
