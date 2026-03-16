<?php

namespace Tests\Unit;

use Edrard\Pingmonit\Ups\UpsMonitor;
use Edrard\Pingmonit\Ups\UpsSnmpClient;
use Edrard\Pingmonit\State\HostStateRepository;
use Edrard\Pingmonit\State\JsonStateStore;
use Edrard\Pingmonit\Contracts\UpsNotifierInterface;
use Mockery;

test('UPS determines correct status based on capacity - simple', function () {
    // Create mock objects with expectations
    $stateStore = Mockery::mock(JsonStateStore::class);
    $stateStore->shouldReceive('load')->andReturn([]);
    $stateStore->shouldReceive('save')->once();
    
    $snmp = Mockery::mock(UpsSnmpClient::class);
    $snmp->shouldReceive('getInt')->andReturn(40); // Below critical threshold
    $snmp->shouldReceive('getTimeTicksSeconds')->andReturn(3600);
    $snmp->shouldReceive('getInt')->andReturn(2);
    
    $notifier = Mockery::mock(UpsNotifierInterface::class);
    $notifier->shouldReceive('notifyCritical')
        ->once()
        ->with('192.168.1.100', 'Test UPS', Mockery::type('array'));
    
    // Create monitor after expectations are set
    $state = new HostStateRepository($stateStore);
    $monitor = new UpsMonitor($state, $snmp, $notifier);
    
    // Test UPS configuration
    $upsConfig = [
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
    ];
    
    // Run UPS check
    $monitor->checkUps([$upsConfig]);
    
    // Verify result
    $savedState = $state->get('192.168.1.100');
    expect($savedState['status'])->toBe('critical');
    
    Mockery::close();
});
