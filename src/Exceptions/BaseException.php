<?php

namespace Edrard\Exceptions;

use edrard\Log\MyLog;
use Exception;

class BaseException extends Exception
{
    /**
    * put your comment there...
    *
    * @param string $message
    * @return Exception
    */
    public function __construct($message)
    {
        $args = func_get_args();
        $message = $this->create($args);
        $code = isset($args[2]) ? (int) $args[2] : 0;
        parent::__construct($message, $code);
    }
    /**
    * put your comment there...
    *
    * @param array $args
    */
    protected function create(array $args)
    {
        if (isset($args[1])) {
            MyLog::{$args[1]}('['.static::class.'] '.$args[0]);
        }
        return $args[0];
    }
}