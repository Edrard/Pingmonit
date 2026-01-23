<?php

namespace Edrard\Pingmonit\Contracts;

interface PingServiceInterface
{
    public function ping($ip, $timeoutSeconds);
}
