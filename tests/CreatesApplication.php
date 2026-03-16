<?php

namespace Tests;

use Edrard\Pingmonit\Config;

trait CreatesApplication
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Don't clear existing configuration to avoid modifying core code
        // Tests will work with existing or set their own config
    }
    
    protected function tearDown(): void
    {
        // Clean up is handled by individual tests
        parent::tearDown();
    }
    
    protected function createTestConfig(): void
    {
        Config::set('ips', [
            '8.8.8.8' => [
                'name' => 'Google DNS',
                'send_email' => false,
                'send_telegram' => false
            ]
        ]);
        
        Config::set('ups', [
            [
                'name' => 'Test UPS',
                'ip' => '192.168.1.100',
                'send_email' => false,
                'send_telegram' => false,
                'oid_capacity' => '1.3.6.1.4.1.318.1.1.1.2.2.1.0',
                'oid_runtime' => '1.3.6.1.4.1.318.1.1.1.2.3.1.0',
                'oid_time_on_battery' => '1.3.6.1.4.1.318.1.1.1.2.1.1.0',
                'oid_battery_status' => '1.3.6.1.4.1.318.1.1.1.2.1.2.0',
                'thresholds' => [
                    'warning' => 90,
                    'critical' => 50
                ]
            ]
        ]);
        
        Config::set('state_file', createTempFile('test_state.json'));
        Config::set('ups_state_file', createTempFile('test_ups_state.json'));
        Config::set('monitoring', [
            'ping_timeout' => 5,
            'max_failures' => 3
        ]);
    }
}
