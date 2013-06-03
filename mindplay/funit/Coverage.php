<?php

namespace mindplay\funit;

/**
 * Interface for code-coverage providers
 */
interface Coverage
{
    public function enable(TestSuite $suite);

    public function disable(TestSuite $suite);

    /**
     * @param TestSuite $suite
     * @return FileCoverage[]
     */
    public function getCoverage(TestSuite $suite);
}
