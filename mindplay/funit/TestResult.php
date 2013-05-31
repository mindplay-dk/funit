<?php

namespace mindplay\funit;

/**
 * This class represents the state/result of an individual test.
 */
class TestResult
{
    public $name;

    public $run = false;

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