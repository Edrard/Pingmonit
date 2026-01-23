<?php

namespace Edrard\Pingmonit\Contracts;

interface NotifierInterface
{
    public function notifyDown($ip, $hostname, $failureCount);

    public function notifyUp($ip, $hostname, $downtimeSeconds);
}
