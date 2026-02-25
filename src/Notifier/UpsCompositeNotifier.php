<?php

namespace Edrard\Pingmonit\Notifier;

use Edrard\Pingmonit\Contracts\UpsNotifierInterface;

class UpsCompositeNotifier implements UpsNotifierInterface
{
    private $notifiers;

    public function __construct(array $notifiers)
    {
        $this->notifiers = $notifiers;
    }

    public function notifyCritical($ip, $name, array $metrics)
    {
        foreach ($this->notifiers as $notifier) {
            if ($notifier instanceof UpsNotifierInterface) {
                $notifier->notifyCritical($ip, $name, $metrics);
            }
        }
    }

    public function notifyRecovered($ip, $name, $downtimeSeconds, array $metrics)
    {
        foreach ($this->notifiers as $notifier) {
            if ($notifier instanceof UpsNotifierInterface) {
                $notifier->notifyRecovered($ip, $name, (int) $downtimeSeconds, $metrics);
            }
        }
    }
}
