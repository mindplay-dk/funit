<?php

namespace mindplay\funit;

use Exception;

/**
 * This Exception is thrown by the error-handler to interrupt execution for reported errors.
 *
 * @see TestSuite::error_handler()
 */
class HandledError extends Exception
{
    /**
     * @var int
     */
    public $errno;

    /**
     * @param int $errno
     * @param string $message
     */
    public function __construct($errno, $message)
    {
        parent::__construct($message);

        $this->errno = $errno;
    }
}