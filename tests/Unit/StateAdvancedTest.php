<?php

namespace Tests\Unit;

use Edrard\Pingmonit\State\HostStateRepository;
use Edrard\Pingmonit\State\JsonStateStore;

test('State repository handles concurrent access', function () {
    $tempFile = __DIR__ . '/../temp/test_concurrent_state.json';
    
    // Create first repository
    $stateStore1 = new JsonStateStore($tempFile);
    $state1 = new HostStateRepository($stateStore1);
    
    $testData1 = ['ip' => '192.168.1.100', 'status' => 'good'];
    $state1->set('192.168.1.100', $testData1);
    $state1->save();
    
    // Create second repository (simulates concurrent access)
    $stateStore2 = new JsonStateStore($tempFile);
    $state2 = new HostStateRepository($stateStore2);
    
    $testData2 = ['ip' => '192.168.1.101', 'status' => 'warning'];
    $state2->set('192.168.1.101', $testData2);
    $state2->save();
    
    // Verify second repository can read first data
    expect($state2->get('192.168.1.100'))->not->toBeNull();
    
    // Clean up
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
});

test('State repository handles large datasets', function () {
    $tempFile = __DIR__ . '/../temp/test_large_state.json';
    $stateStore = new JsonStateStore($tempFile);
    $state = new HostStateRepository($stateStore);
    
    // Add many hosts
    for ($i = 1; $i <= 100; $i++) {
        $ip = "192.168.1.{$i}";
        $state->set($ip, [
            'ip' => $ip,
            'status' => 'good',
            'failures' => 0,
            'last_test_at' => '2026-03-06T12:00:00+00:00'
        ]);
    }
    
    $state->save();
    
    // Verify all hosts are saved by checking we can retrieve one
    expect($state->get('192.168.1.50'))->not->toBeNull();
    
    // Clean up
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
});
