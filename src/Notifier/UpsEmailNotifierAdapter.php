<?php

namespace Edrard\Pingmonit\Notifier;

use Edrard\Pingmonit\Contracts\UpsNotifierInterface;
use Edrard\Pingmonit\EmailNotifier;

class UpsEmailNotifierAdapter implements UpsNotifierInterface
{
    private $inner;

    public function __construct(EmailNotifier $inner)
    {
        $this->inner = $inner;
    }

    public function notifyCritical($ip, $name, array $metrics)
    {
        $displayName = $ip;
        if (is_string($name) && $name !== '' && $name !== $ip) {
            $displayName = $ip . ' (' . $name . ')';
        }

        $subject = "UPS critical: {$displayName}";

        $body = "UPS {$displayName} is in CRITICAL state.\n";
        $body .= 'Capacity (%): ' . ($metrics['capacity'] ?? 'n/a') . "\n";
        $body .= 'Runtime (sec): ' . ($metrics['runtime_seconds'] ?? 'n/a') . "\n";
        $body .= 'Time on battery (sec): ' . ($metrics['time_on_battery_seconds'] ?? 'n/a') . "\n";
        $body .= 'Battery status: ' . ($metrics['battery_status'] ?? 'n/a') . "\n";
        $body .= 'Time: ' . date('Y-m-d H:i:s') . "\n";

        return $this->inner->sendNotification($subject, $body);
    }

    public function notifyRecovered($ip, $name, $downtimeSeconds, array $metrics)
    {
        $displayName = $ip;
        if (is_string($name) && $name !== '' && $name !== $ip) {
            $displayName = $ip . ' (' . $name . ')';
        }

        $subject = "UPS recovered: {$displayName}";

        $body = "UPS {$displayName} is OK again.\n";
        $body .= 'Capacity (%): ' . ($metrics['capacity'] ?? 'n/a') . "\n";
        $body .= 'Runtime (sec): ' . ($metrics['runtime_seconds'] ?? 'n/a') . "\n";
        $body .= 'Time on battery (sec): ' . ($metrics['time_on_battery_seconds'] ?? 'n/a') . "\n";
        $body .= 'Battery status: ' . ($metrics['battery_status'] ?? 'n/a') . "\n";

        $downtimeSeconds = (int) $downtimeSeconds;
        if ($downtimeSeconds > 0) {
            $body .= 'Downtime (sec): ' . $downtimeSeconds . "\n";
        }

        $body .= 'Time: ' . date('Y-m-d H:i:s') . "\n";

        return $this->inner->sendNotification($subject, $body);
    }
}
