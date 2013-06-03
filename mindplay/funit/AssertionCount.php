<?php

namespace mindplay\funit;

/**
 * This class represents a tally of Assertion results
 */
class AssertionCount
{
    /**
     * @var int
     */
    public $count = 0;

    /**
     * @var int
     */
    public $passed = 0;

    /**
     * @var int
     */
    public $failed = 0;

    /**
     * @var int
     */
    public $warnings = 0;

    /**
     * @param Assertion $assertion
     * @see __construct()
     */
    protected function tally(Assertion $assertion)
    {
        if ($assertion->result) {
            $this->passed ++;
        } else {
            if ($assertion->is_warning) {
                $this->warnings ++;
            } else {
                $this->failed ++;
            }
        }

        $this->count ++;
    }

    /**
     * @param Assertion[] $assertions list of Assertions to be counted
     */
    public function __construct($assertions = array())
    {
        foreach ($assertions as $assertion) {
            $this->tally($assertion);
        }
    }
}
