<?php

namespace mindplay\funit;

/**
 * Code-coverage provider for XDebug
 *
 * Also compatible with Zend Debugger, but Zend Optimizer must be turned off, and the
 * following configuration directives must be set in "etc/config/debugger.ini":
 *
 *   zend_debugger.enable_coverage=1
 *   zend_debugger.xdebug_compatible_coverage=1
 *
 * You must start the debugging-session, e.g. by adding "?start_debug=1" to the URL.
 */
class XDebugCoverage implements Coverage
{
    public function enable(TestSuite $fu)
    {
        xdebug_stop_code_coverage(true);
        xdebug_start_code_coverage(XDEBUG_CC_UNUSED + XDEBUG_CC_DEAD_CODE);
    }

    public function disable(TestSuite $fu)
    {
        xdebug_stop_code_coverage(false);
    }

    public function get_results(TestSuite $fu)
    {
        /**
         * @var $files FileCoverage[]
         */

        static $uncovered = array(
            '',
            '}',
            'else'
        ); // we can safely ignore uncovered empty lines, closing braces and else-clauses that don't have a statement

        $files = array();

        foreach (xdebug_get_code_coverage() as $path => $lines) {
            $file = new FileCoverage($path);

            foreach ($lines as $line => $coverage) {
                if (in_array(trim($lines[$line]), $uncovered) || $coverage !== - 1) {
                    $file->cover($line);
                }
            }

            $files[] = $file;
        }

        return $files;
    }
}