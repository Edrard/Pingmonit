<?php

namespace Edrard\Pingmonit\Notifier;

use Edrard\Pingmonit\Contracts\NotifierInterface;

class CompositeNotifier implements NotifierInterface
{
    private $notifiers;

    public function __construct(array $notifiers)
    {
        $this->notifiers = $notifiers;
    }

    public function notifyDown($ip, $hostname, $failureCount)
    {
        foreach ($this->notifiers as $notifier) {
            if ($notifier instanceof NotifierInterface) {
                $notifier->notifyDown($ip, $hostname, $failureCount);
            }
        }
    }

    public function notifyUp($ip, $hostname, $downtimeSeconds)
    {
        foreach ($this->notifiers as $notifier) {
            if ($notifier instanceof NotifierInterface) {
                $notifier->notifyUp($ip, $hostname, $downtimeSeconds);
            }
        }
    }
}
