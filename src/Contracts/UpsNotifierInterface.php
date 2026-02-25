<?php

namespace Edrard\Pingmonit\Contracts;

interface UpsNotifierInterface
{
    public function notifyCritical($ip, $name, array $metrics);

    public function notifyRecovered($ip, $name, $downtimeSeconds, array $metrics);
}
