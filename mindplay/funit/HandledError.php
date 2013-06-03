<?php

namespace mindplay\funit;

use Exception;

/**
 * This Exception is thrown by the error-handler to interrupt execution
 * when a non-resumable PHP error (not Exception) occurs and is handled.
 *
 * @see TestSuite::error_handler(handleError
 */
class HandledError extends Exception
{
    /**
     * @var int error code (e.g. E_WARNING, E_NOTICE, etc.)
     */
    public $code;

    /**
     * @param int $code numeric error-code
     * @param string $message descriptive error message
     */
    public function __construct($code, $message)
    {
        parent::__construct($message);

        $this->code = $code;
    }
}