<?php

namespace mindplay\funit;

/**
 * This base-class defines the interface and properties of a Report-class
 */
abstract class Report
{
    /**
     * @var bool toggle verbose report output (backtraces and diagnostic information)
     */
    public $debug = false;

    abstract public function renderHeader(TestSuite $suite);

    abstract public function renderMessage($message, $debug = false);

    abstract public function renderBody(TestSuite $suite);

    abstract public function renderFooter(TestSuite $suite);
}
