<?php

namespace mindplay\funit;

/**
 * This class represents an Assertion performed during a Test.
 *
 * @property-read string $formatted arguments formatted as a string for display
 */
class Assertion extends Accessors
{
    /**
     * @var string name of the TestSuite method that generated this Assertion
     */
    public $func_name;

    /**
     * @var array the arguments that were used to perform this Assertion
     */
    public $func_args;

    /**
     * @var bool the result of the Assertion (true = passed, false = failed)
     */
    public $result = false;

    /**
     * @var string|null an optional message describing the conditions under which this Assertion should pass or fail
     */
    public $msg;

    /**
     * @var string|null an optional description of the Assertion that was performed
     */
    public $description;

    /**
     * @var bool true, if the Assertion was failed in an expected way
     */
    public $is_warning = false;

    /**
     * @var int|string expected (integer) error-code, or the name of the expected Exception-type (string)
     */
    public $expected_error = null;

    /**
     * @param string $func_name name of the TestSuite method that generated this Assertion
     * @param array $func_args the arguments that were used to perform this Assertion
     * @param bool $result the result of the Assertion (true = passed, false = failed)
     * @param string|null $msg an optional message describing the conditions under which this Assertion should pass or fail
     * @param string|null $description an optional description of the Assertion that was performed
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
     * @see $formatted
     */
    protected function get_formatted()
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