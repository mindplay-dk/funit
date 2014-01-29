<?php

namespace mindplay\funit;

/**
 * This class represents the net result of running a TestSuite
 *
 * @see TestSuite::test_counts()
 *
 * @property-read bool $success
 */
class TestResult extends Accessors
{
    /**
     * @var int total number of Tests found in the TestSuite
     */
    public $total = 0;

    /**
     * @var int total number of Tests that were run (same as $total unless some Tests were omitted by filtering)
     */
    public $run = 0;

    /**
     * @var int total number of Tests passed
     */
    public $passed = 0;

    /**
     * @see $success
     */
    protected function get_success()
    {
        return ($this->run > 0)
            && ($this->run === $this->passed);
    }
}
