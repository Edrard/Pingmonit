<?php

namespace Tests\Unit;

use Edrard\Pingmonit\Notifier\UpsEmailNotifierAdapter;
use Edrard\Pingmonit\EmailNotifier;
use Mockery;

test('Email notifier sends critical notification', function () {
    $emailNotifier = Mockery::mock(EmailNotifier::class);
    $emailNotifier->shouldReceive('sendNotification')
        ->once()
        ->with(
            'UPS critical: 192.168.1.100 (Test Server)', 
            Mockery::type('string')
        );
    
    $adapter = new UpsEmailNotifierAdapter($emailNotifier);
    $metrics = ['capacity' => 45, 'runtime_seconds' => 1800];
    
    // Test that method doesn't throw exception
    expect(fn() => $adapter->notifyCritical('192.168.1.100', 'Test Server', $metrics))->not->toThrow(\Exception::class);
    
    Mockery::close();
});

test('Email notifier handles empty name', function () {
    $emailNotifier = Mockery::mock(EmailNotifier::class);
    $emailNotifier->shouldReceive('sendNotification')
        ->once()
        ->with(
            'UPS critical: 192.168.1.100', 
            Mockery::type('string')
        );
    
    $adapter = new UpsEmailNotifierAdapter($emailNotifier);
    $metrics = ['capacity' => 45, 'runtime_seconds' => 1800];
    
    // Test that method doesn't throw exception with empty name
    expect(fn() => $adapter->notifyCritical('192.168.1.100', '', $metrics))->not->toThrow(\Exception::class);
    
    Mockery::close();
});
