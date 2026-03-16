<?php

namespace Tests\Unit;

use Edrard\Pingmonit\Notifier\UpsEmailNotifierAdapter;
use Edrard\Pingmonit\Notifier\UpsTelegramNotifierAdapter;
use Edrard\Pingmonit\Notifier\UpsCompositeNotifier;
use Edrard\Pingmonit\EmailNotifier;
use Mockery;

test('Composite notifier sends to all enabled channels', function () {
    $emailNotifier = Mockery::mock(EmailNotifier::class);
    $emailNotifier->shouldReceive('sendNotification')->once();
    
    $emailAdapter = new UpsEmailNotifierAdapter($emailNotifier);
    $composite = new UpsCompositeNotifier(
        [$emailAdapter], 
        true, 
        false  
    );
    
    $metrics = ['capacity' => 45, 'runtime_seconds' => 1800];
    
    expect(fn() => $composite->notifyCritical('192.168.1.100', 'Test Server', $metrics))->not->toThrow(\Exception::class);
    
    Mockery::close();
});

test('Composite notifier respects disabled channels', function () {
    $emailNotifier = Mockery::mock(EmailNotifier::class);
    $emailNotifier->shouldNotReceive('sendNotification');
    
    $emailAdapter = new UpsEmailNotifierAdapter($emailNotifier);
    $composite = new UpsCompositeNotifier(
        [$emailAdapter], 
        false, 
        false  
    );
    
    $metrics = ['capacity' => 45, 'runtime_seconds' => 1800];
    
    expect(fn() => $composite->notifyCritical('192.168.1.100', 'Test Server', $metrics))->not->toThrow(\Exception::class);
    
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
    
    expect(fn() => $adapter->notifyCritical('192.168.1.100', '', $metrics))->not->toThrow(\Exception::class);
    
    Mockery::close();
});
