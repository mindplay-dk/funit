<?php

namespace mindplay\funit;

/**
 * Interface for code-coverage providers
 */
interface Coverage
{
    public function enable(Test $fu);

    public function disable(Test $fu);

    /**
     * @param Test $fu
     *
     * @return FileCoverage[]
     */
    public function get_results(Test $fu);
}
