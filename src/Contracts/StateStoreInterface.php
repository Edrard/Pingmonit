<?php

namespace Edrard\Pingmonit\Contracts;

interface StateStoreInterface
{
    public function load();

    public function save(array $state);
}
