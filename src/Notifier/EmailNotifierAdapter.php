<?php

namespace Edrard\Pingmonit\Notifier;

use Edrard\Pingmonit\Contracts\NotifierInterface;
use Edrard\Pingmonit\EmailNotifier;

class EmailNotifierAdapter implements NotifierInterface
{
    private $inner;

    public function __construct(EmailNotifier $inner)
    {
        $this->inner = $inner;
    }

    public function notifyDown($ip, $hostname, $failureCount)
    {
        return $this->inner->sendServerDownNotification($ip, (string) $hostname, (int) $failureCount);
    }

    public function notifyUp($ip, $hostname, $downtimeSeconds)
    {
        return $this->inner->sendServerUpNotification($ip, (string) $hostname, (int) $downtimeSeconds);
    }
}
