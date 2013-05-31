<?php

namespace mindplay\funit;

use Exception;

/**
 * This Exception is thrown by the error-handler to interrupt execution for reported errors.
 *
 * @see fu::error_handler()
 */
class HandledError extends Exception
{
    public $errno;

    public function __construct($errno, $message)
    {
        parent::__construct($message);

        $this->errno = $errno;
    }
}