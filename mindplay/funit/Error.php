<?php

namespace mindplay\funit;

/**
 * This class represents a recorded error or exception, and backtrace information.
 */
class Error
{
    /**
     * @var int numeric error code
     */
    public $code;

    /**
     * @var string description of the type of error (e.g. 'E_SOMETHING' or 'FooException', etc.)
     */
    public $type;

    /**
     * @var string the error-message that was produced
     */
    public $message;

    /**
     * @var string absolute path to the file where the error or exception was encountered
     */
    public $file;

    /**
     * @var int line number in the source-file where the error or exception was encountered
     */
    public $line;

    /**
     * @var bool indicates whether this error was expected by an assertion
     */
    public $expected = false;

    /**
     * @var string[] backtrace information
     * @see add_backtrace(addBacktrace
     */
    public $backtrace = array();

    /**
     * @param int $code numeric error code
     * @param string $type description of the type of error (e.g. 'E_SOMETHING' or 'FooException', etc.)
     * @param string $message the error-message that was produced
     * @param string $file absolute path to the file where the error or exception was encountered
     * @param int $line line number in the source-file where the error or exception was encountered
     */
    public function __construct($code, $type, $message, $file, $line)
    {
        $this->code = $code;
        $this->type = $type;
        $this->message = $message;
        $this->file = $file;
        $this->line = $line;
    }

    /**
     * Add backtrace data to this Error
     *
     * @param array $backtrace a backtrace array, such as produced by debug_backtrace() and Exception::getTrace()
     *
     * @see debug_backtrace()
     * @see Exception::getTrace()
     */
    public function setBacktrace($backtrace)
    {
        $this->backtrace = array();

        foreach ($backtrace as $bt) {
            $str = '';

            if (isset($bt['file']) && $bt['file'] === __FILE__) {
                continue; // skip backtraces from this file
            }

            if (isset($bt['file'])) {
                $str .= $bt['file'] . '#' . $bt['line'];
            }

            if (isset($bt['class']) && isset($bt['function'])) {
                $str .= " {$bt['class']}::{$bt['function']}(...)";
            } elseif (isset($bt['function'])) {
                $str .= " {$bt['function']}(...)";
            }

            $this->backtrace[] = $str;
        }
    }
}