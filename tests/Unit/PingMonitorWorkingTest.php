<?php

namespace Tests\Unit;

use Edrard\Pingmonit\PingMonitor;
use Edrard\Pingmonit\Ping\JjgPingService;
use Edrard\Pingmonit\State\HostStateRepository;
use Edrard\Pingmonit\State\JsonStateStore;
use Edrard\Pingmonit\Contracts\NotifierInterface;
use Mockery;

test('Ping monitor detects host failure', function () {
    // Mock dependencies
    $stateStore = Mockery::mock(JsonStateStore::class);
    $stateStore->shouldReceive('load')->andReturn([
        '8.8.8.8' => ['status' => 'good', 'failures' => 0]
    ]);
    $stateStore->shouldReceive('save')->once();
    
    $pingService = Mockery::mock(JjgPingService::class);
    $pingService->shouldReceive('ping')->andReturn(null); // Ping fails
    
    $notifier = Mockery::mock(NotifierInterface::class);
    
    $state = new HostStateRepository($stateStore);
    $monitor = new PingMonitor($state, $pingService, $notifier, [
        'ping_timeout' => 5,
        'max_failures' => 3
    ]);
    
    $hostConfig = [
        'ip' => '8.8.8.8',
        'name' => 'Google DNS',
        'send_email' => true,
        'send_telegram' => true
    ];
    
    $monitor->checkHosts([$hostConfig]);
    
    // Verify state updated
    $savedState = $state->get('8.8.8.8');
    expect($savedState['status'])->toBe('warning');
    expect($savedState['failures'])->toBe(1);
    
    Mockery::close();
});

test('Ping monitor detects host recovery', function () {
    // Mock dependencies
    $stateStore = Mockery::mock(JsonStateStore::class);
    $stateStore->shouldReceive('load')->andReturn([
        '8.8.8.8' => ['status' => 'critical', 'failures' => 5]
    ]);
    $stateStore->shouldReceive('save')->once();
    
    $pingService = Mockery::mock(JjgPingService::class);
    $pingService->shouldReceive('ping')->andReturn(25.5); // Ping succeeds
    
    $notifier = Mockery::mock(NotifierInterface::class);
    $notifier->shouldReceive('notifyUp')->once();
    
    $state = new HostStateRepository($stateStore);
    $monitor = new PingMonitor($state, $pingService, $notifier, [
        'ping_timeout' => 5,
        'max_failures' => 3
    ]);
    
    $hostConfig = [
        'ip' => '8.8.8.8',
        'name' => 'Google DNS',
        'send_email' => true,
        'send_telegram' => true
    ];
    
    $monitor->checkHosts([$hostConfig]);
    
    // Verify state updated
    $savedState = $state->get('8.8.8.8');
    expect($savedState['status'])->toBe('good');
    expect($savedState['failures'])->toBe(0);
    
    Mockery::close();
});
