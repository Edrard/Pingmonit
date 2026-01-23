<?php

namespace Edrard\Pingmonit\Contracts;

interface LockInterface
{
    public function acquire();

    public function release();
}
