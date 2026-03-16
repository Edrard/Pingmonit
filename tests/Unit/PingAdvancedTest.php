<?php

namespace Tests\Unit;

use Edrard\Pingmonit\PingMonitor;
use Edrard\Pingmonit\Ping\JjgPingService;
use Edrard\Pingmonit\State\HostStateRepository;
use Edrard\Pingmonit\State\JsonStateStore;
use Edrard\Pingmonit\Contracts\NotifierInterface;
use Mockery;

test('Ping monitor handles timeout correctly', function () {
    $stateStore = Mockery::mock(JsonStateStore::class);
    $stateStore->shouldReceive('load')->andReturn([
        '8.8.8.8' => ['status' => 'good', 'failures' => 0]
    ]);
    $stateStore->shouldReceive('save')->once();
    
    $pingService = Mockery::mock(JjgPingService::class);
    $pingService->shouldReceive('ping')->andReturn(null); // Timeout/failure
    
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
    
    // Verify failure count increased
    $savedState = $state->get('8.8.8.8');
    expect($savedState['status'])->toBe('warning');
    expect($savedState['failures'])->toBe(1);
    
    Mockery::close();
});

test('Ping monitor tracks latency correctly', function () {
    $stateStore = Mockery::mock(JsonStateStore::class);
    $stateStore->shouldReceive('load')->andReturn([
        '8.8.8.8' => ['status' => 'good', 'failures' => 0]
    ]);
    $stateStore->shouldReceive('save')->once();
    
    $pingService = Mockery::mock(JjgPingService::class);
    $pingService->shouldReceive('ping')->andReturn(25.5); // Successful ping
    
    $notifier = Mockery::mock(NotifierInterface::class);
    $notifier->shouldNotReceive('notifyDown');
    $notifier->shouldNotReceive('notifyUp');
    
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
    
    // Verify latency is tracked
    $savedState = $state->get('8.8.8.8');
    expect($savedState['status'])->toBe('good');
    expect($savedState['failures'])->toBe(0);
    expect($savedState['latency_ms'])->toBe(25.5);
    
    Mockery::close();
});
