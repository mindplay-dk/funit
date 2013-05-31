<?php

namespace mindplay\funit;

/**
 * This class represents the state/result of an individual test.
 */
class Test
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var bool
     */
    public $run = false;

    /**
     * @var bool
     */
    public $pass = false;

    /**
     * @var string test method-name
     */
    public $method;

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
     * @see TestSuite::run_test()
     */
    public $timing = array();

    public function __construct($name, $method)
    {
        $this->name = $name;
        $this->method = $method;
    }

    public function get_assertion_count()
    {
        return new AssertionCount($this->assertions);
    }
}
