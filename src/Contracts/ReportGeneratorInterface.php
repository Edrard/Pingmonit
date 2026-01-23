<?php

namespace Edrard\Pingmonit\Contracts;

use Edrard\Pingmonit\State\HostStateRepository;

interface ReportGeneratorInterface
{
    public function generate(array $targets, HostStateRepository $state);
}
