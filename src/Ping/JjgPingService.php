<?php

namespace Edrard\Pingmonit\Ping;

use Edrard\Pingmonit\Contracts\PingServiceInterface;

class JjgPingService implements PingServiceInterface
{
    public function ping($ip, $timeoutSeconds)
    {
        $ping = new \JJG\Ping($ip);
        $ping->setTimeout((int) $timeoutSeconds);
        $latency = $ping->ping();

        if ($latency === false || $latency === null) {
            return null;
        }

        return (float) $latency;
    }
}
