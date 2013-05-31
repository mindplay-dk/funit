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
    public $pass = 0;

    /**
     * @var int
     */
    public $fail = 0;

    /**
     * @var int
     */
    public $expected_fail = 0;

    /**
     * @param Assertion $assertion
     */
    public function tally(Assertion $assertion)
    {
        if ($assertion->result) {
            $this->pass ++;
        } else {
            if ($assertion->expected_fail) {
                $this->expected_fail ++;
            } else {
                $this->fail ++;
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