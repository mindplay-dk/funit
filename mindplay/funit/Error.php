<?php

namespace mindplay\funit;

/**
 * This class represents a recorded error or exception, and backtrace information.
 */
class Error
{
    /**
     * @var string
     */
    public $datetime;

    /**
     * @var int
     */
    public $num;

    /**
     * @var string
     */
    public $type;

    /**
     * @var string
     */
    public $msg;

    /**
     * @var string
     */
    public $file;

    /**
     * @var int
     */
    public $line;

    /**
     * @var bool
     */
    public $expected = false;

    /**
     * @var string[] backtrace statements
     */
    public $backtrace = array();

    public function __construct($num, $type, $msg, $file, $line)
    {
        $this->datetime = date("Y-m-d H:i:s (T)");

        $this->num = $num;
        $this->type = $type;
        $this->msg = $msg;
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
    public function add_backtrace($backtrace)
    {
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