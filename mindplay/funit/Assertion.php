<?php

namespace mindplay\funit;

/**
 * This class represents an Assertion performed during a Test.
 */
class Assertion
{
    /**
     * @var string
     */
    public $func_name;

    /**
     * @var array
     */
    public $func_args;

    /**
     * @var mixed
     */
    public $result;

    /**
     * @var string|null
     */
    public $msg;

    /**
     * @var string|null
     */
    public $description;

    /**
     * @var bool true, if the Assertion was failed in an expected way
     */
    public $is_warning = false;

    /**
     * @var null
     */
    public $expected_error = null;

    /**
     * @param string $func_name
     * @param array $func_args
     * @param mixed $result
     * @param string|null $msg
     * @param string|null $description
     */
    public function __construct($func_name, $func_args, $result, $msg = null, $description = null)
    {
        $this->func_name = $func_name;
        $this->func_args = $func_args;
        $this->result = $result;
        $this->msg = $msg;
        $this->description = $description;
    }

    /**
     * @return string
     */
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