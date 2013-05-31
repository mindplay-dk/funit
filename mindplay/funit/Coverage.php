<?php

namespace mindplay\funit;

/**
 * Interface for code-coverage providers
 */
interface Coverage
{
    public function enable(TestSuite $fu);

    public function disable(TestSuite $fu);

    /**
     * @param TestSuite $fu
     *
     * @return FileCoverage[]
     */
    public function get_results(TestSuite $fu);
}
