<?php

namespace mindplay\funit;

/**
 * This class represents the state/result of an individual test.
 *
 * @property-read AssertionCount $assertion_count
 */
class Test extends Accessors
{
    /**
     * @var string display-friendly test name
     */
    public $name;

    /**
     * @var string TestSuite method-name
     */
    public $method;

    /**
     * @var bool flag indicating whether this Test has been run
     */
    public $run = false;

    /**
     * @var bool flag indicating whether this Tast has passed
     */
    public $passed = false;

    /**
     * @var Error[] list of Errors caught while running this Test
     */
    public $errors = array();

    /**
     * @var Assertion[] list of Assertions performed while running this Test
     */
    public $assertions = array();

    /**
     * @var array[] timing information captured while running a test
     * @see TestSuite::run_test(runTest
     */
    public $timing = array();

    /**
     * @param string $name display-friendly test name
     * @param string $method TestSuite method-name
     */
    public function __construct($name, $method)
    {
        $this->name = $name;
        $this->method = $method;
    }

    /**
     * @see $assertion_count
     */
    protected function get_assertion_count()
    {
        return new AssertionCount($this->assertions);
    }
}
