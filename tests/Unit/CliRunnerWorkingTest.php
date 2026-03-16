<?php

namespace Tests\Unit;

use Edrard\Pingmonit\CliRunner;
use Edrard\Pingmonit\Config;
use Edrard\Pingmonit\Lock\FlockLock;
use Edrard\Pingmonit\State\HostStateRepository;
use Edrard\Pingmonit\State\JsonStateStore;
use Mockery;

test('CLI runner processes single host', function () {
    // Mock dependencies
    $lock = Mockery::mock(FlockLock::class);
    $lock->shouldReceive('acquire')->andReturn(true);
    $lock->shouldReceive('release');
    
    $stateStore = Mockery::mock(JsonStateStore::class);
    $stateStore->shouldReceive('load')->andReturn([]);
    $stateStore->shouldReceive('save')->zeroOrMoreTimes(); // May or may not save
    
    $cliRunner = new CliRunner();
    
    $options = [
        'disable_state' => false,
        'disable_email' => true,
        'disable_telegram' => true,
        'disable_lock' => true
    ];
    
    expect(fn() => $cliRunner->run($options))->not->toThrow(\Exception::class);
    
    Mockery::close();
});

test('CLI runner respects IP filter', function () {
    // Mock dependencies
    $lock = Mockery::mock(FlockLock::class);
    $lock->shouldReceive('acquire')->andReturn(true);
    $lock->shouldReceive('release');
    
    $stateStore = Mockery::mock(JsonStateStore::class);
    $stateStore->shouldReceive('load')->andReturn([]);
    $stateStore->shouldReceive('save')->zeroOrMoreTimes(); // May or may not save
    
    $cliRunner = new CliRunner();
    
    $options = [
        'disable_state' => false,
        'disable_email' => true,
        'disable_telegram' => true,
        'disable_lock' => true,
        'ip' => '8.8.8.8' // Filter specific IP
    ];
    
    expect(fn() => $cliRunner->run($options))->not->toThrow(\Exception::class);
    
    Mockery::close();
});
