<?php

namespace mindplay\funit;

/**
 * This base-class defines the interface and properties of a Report-class
 */
abstract class Report
{
    /**
     * @var bool toggle verbose report output (with backtraces)
     */
    public $debug = false;

    abstract public function render_header(Test $fu);

    abstract public function render_message($msg, $debug = false);

    abstract public function render_body(Test $fu);

    abstract public function render_footer(Test $fu);
}