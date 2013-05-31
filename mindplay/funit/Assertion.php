<?php

namespace mindplay\funit;

/**
 * This class represents an Assertion performed during a Test.
 */
class Assertion
{
    public $func_name;
    public $func_args;
    public $result;
    public $msg;
    public $description;
    public $expected_fail = false;
    public $expected_error = null;

    public function __construct($func_name, $func_args, $result, $msg = null, $description = null)
    {
        $this->func_name = $func_name;
        $this->func_args = $func_args;
        $this->result = $result;
        $this->msg = $msg;
        $this->description = $description;
    }

    public function format_args()
    {
        $strings = array_map(
            function ($var) {
                return var_export($var, true);
            },
            $this->func_args
        );

        return strtr(implode(', ', $strings), array("\r" => "", "\n" => ""));
    }
}