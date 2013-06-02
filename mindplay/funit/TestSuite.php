<?php

namespace mindplay\funit;

use Closure;
use Exception;
use ReflectionClass;
use ReflectionMethod;

/**
 * @property Test[] $tests
 * @property-read TestResult $results summary of results from running the TestSuite
 */
abstract class TestSuite extends Accessors
{
    /**
     * @var array map where error-code => display-name
     */
    public $error_labels = array();

    /**
     * @var array list of warning-level error codes after which execution may continue
     */
    public $warnings = array(
        E_WARNING,
        E_PARSE,
        E_NOTICE,
        E_USER_WARNING,
        E_USER_NOTICE,
        E_STRICT,
        E_RECOVERABLE_ERROR,
        E_DEPRECATED,
        E_USER_DEPRECATED,
    );

    /**
     * @var string a title for the unit-test
     * @not defaults to the class-name of the concrete test-class
     */
    public $title;

    /**
     * @var Test[] individual tests configured for this test-suite.
     */
    public $tests = array();

    /**
     * @var Report the Report to render on run()
     * @see run()
     */
    public $report = null;

    /**
     * @var Test the Test that is currently being run
     */
    public $current_test = null;

    /**
     * TODO replace this feature with something cleaner (using Closures)
     *
     * @var mixed[]
     * @see fixture()
     */
    public $fixtures = array();

    /**
     * @var Coverage
     */
    public $coverage;

    public function __construct()
    {
        // configure test title:
        $this->title = get_class($this);

        // configure tests:
        $this->tests = $this->get_tests();

        // configure error-labels:
        $consts = get_defined_constants(true);
        $errors = $consts['Core'];

        foreach ($errors as $name => $value) {
            if (strncmp('E_', $name, 2) === 0) {
                $this->error_labels[$value] = strtr(substr($name, 2), '_', ' ');
            }
        }

        // configure code coverage:
        if (function_exists('xdebug_start_code_coverage')) {
            if (ini_get('xdebug.coverage_enable') || (ini_get('zend_debugger.enable_coverage') && ini_get(
                        'zend_debugger.xdebug_compatible_coverage'
                    ))
            ) {
                $this->coverage = new XDebugCoverage();
            }
        }
    }

    /**
     * Override this to perform common initialization before running a test.
     */
    protected function setup()
    {
        // empty default implementation
    }

    /**
     * Override this to perform clean-up after running a test.
     */
    protected function teardown()
    {
        // empty default implementation
    }

    /**
     * Returns a list of the Tests implemented by the concrete test-class.
     *
     * @return TestSuite[] list of detected Tests
     */
    protected function get_tests()
    {
        /**
         * @var ReflectionMethod $method
         * @var TestSuite[]           $tests
         */

        $class = new ReflectionClass(get_class($this));
        $self = new ReflectionClass(__CLASS__);

        $tests = array();

        foreach ($class->getMethods() as $method) {
            if ($self->hasMethod($method->name)) {
                continue; // skip methods on this class
            }

            if ($method->getDeclaringClass()->isAbstract()) {
                continue; // skip methods on abstract classes
            }

            if (! $method->isPublic()) {
                continue; // skip non-public methods
            }

            $tests[] = new Test(strtr($method->name, '_', ' '), $method->name);
        }

        return $tests;
    }

    /**
     * Custom exception handler - records the exception as an Error instance.
     *
     * @param Exception $e
     * @param bool      $expected
     *
     * @see run_test()
     */
    protected function exception_handler($e, $expected = false)
    {
        $error = new Error(
            0,
            'EXCEPTION: ' . get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );

        $error->expected = $expected;

        $error->add_backtrace($e->getTrace());

        $this->current_test->errors[] = $error;
    }


    /**
     * Custom error handler - records the error-information as an Error instance.
     * This is registered at the start of $this->run() and deregistered at stop.
     *
     * @see run()
     * @see Test::$errors
     */
    public function error_handler($num, $msg, $file, $line, $vars)
    {
        $error = new Error(
            $num,
            $this->error_labels[$num],
            $msg,
            $file,
            $line
        );

        $error->add_backtrace(debug_backtrace());

        $this->current_test->errors[] = $error;

        if (! in_array($num, $this->warnings, true)) {
            // for non-warning level errors, interrupt execution by throwing an Exception:
            throw new HandledError($num, $msg);
        }
    }

    /**
     * Output a string
     *
     * @param $str string message to output
     */
    protected function out($str)
    {
        if ($this->report) {
            $this->report->render_message($str);
        }
    }

    /**
     * Output a debug message .
     *
     * @param $str string debug message to output
     */
    protected function debug_out($str)
    {
        if ($this->report) {
            $this->report->render_message($str, true);
        }
    }

    /**
     * Run a single test of the passed $name
     *
     * @param Test $test the Test to run
     *
     * @internal this method is for internal use within the library
     * @see  run_tests()
     * @see  setup()
     * @see  teardown()
     */
    protected function run_test(Test $test)
    {
        $this->out("Running test \"{$test->name}\" ...");

        $this->current_test = $test;

        // setup:
        $time_started = microtime(true);
        $this->setup();
        $time_after_setup = microtime(true);

        // run test:
        try {
            $this->{$test->method}();
        } catch (Exception $e) {
            if ($e instanceof HandledError) {
                // this error has already been handled
            } else {
                $this->exception_handler($e);
            }
        }

        $time_after_run = microtime(true);

        // clean up:
        $this->teardown();
        $this->reset_fixtures();
        $time_after_teardown = microtime(true);

        $this->current_test = null;
        $test->run = true;
        $test->timing = array(
            'setup'    => $time_after_setup - $time_started,
            'run'      => $time_after_run - $time_after_setup,
            'teardown' => $time_after_teardown - $time_after_run,
            'total'    => $time_after_teardown - $time_started,
        );

        if (count($test->errors) > 0) {
            $test->passed = false;
        } else {
            $count = $test->assertion_count;
            $test->passed = $count->passed === $count->count;
        }

        $this->debug_out("Timing: " . json_encode($test->timing)); // json is easy to read
    }

    /**
     * Run all of the registered tests
     *
     * @note Normally you would not call this method directly
     *
     * @param string $filter optional test case name filter
     *
     * @see  run()
     * @see  run_test()
     */
    protected function run_tests($filter = null)
    {
        foreach ($this->tests as $name => $test) {
            if (null === $filter || (stripos($name, $filter) !== false)) {
                $this->run_test($test);
            }
        }
    }

    /**
     * Run the registered tests, and output a report
     *
     * @param bool|Report $report the Report to render; or true to render the default report, false to disable
     * @param string      $filter optional test case name filter
     * @return int error code (0 or 1, for use with an exit statement to set errorlevel on command-line)
     *
     * @see run_tests()
     */
    public function run($report = true, $filter = null)
    {
        // configure Report:
        if (is_bool($report)) {
            $_report = null;
            if ($report === true) {
                $_report = new ConsoleReport();
                if (! $_report->console) {
                    $_report = new HtmlReport();
                }
            }
            $this->report = $_report;
        } else {
            $this->report = $report;
        }

        // render report header:
        if ($this->report) {
            $this->report->render_header($this);
        }

        // set handlers:
        $old_error_handler = set_error_handler(array($this, 'error_handler'));

        // enable coverage:
        if ($this->coverage) {
            $this->coverage->enable($this);
        }

        // run tests:
        $this->run_tests($filter);

        // disable coverage:
        if ($this->coverage) {
            $this->coverage->disable($this);
        }

        // restore handlers:
        if ($old_error_handler) {
            set_error_handler($old_error_handler);
        }

        // render report:
        if ($this->report) {
            $this->report->render_body($this);
            $this->report->render_footer($this);
        }

        return $this->error_count() > 0 ? 1 : 0;
    }

    /**
     * @return AssertionCount the sum total of all Assertions across all Tests
     */
    public function assertion_counts()
    {
        $total = new AssertionCount();

        foreach ($this->tests as $test) {
            $assert_counts = $test->assertion_count;

            $total->passed += $assert_counts->passed;
            $total->failed += $assert_counts->failed;
            $total->warnings += $assert_counts->warnings;
            $total->count += $assert_counts->count;
        }

        return $total;
    }

    /**
     * @see results
     */
    protected function get_results()
    {
        $result = new TestResult();

        $result->total = count($this->tests);

        foreach ($this->tests as $test) {
            if ($test->run) {
                $result->run ++;
            }
            if ($test->passed) {
                $result->passed ++;
            }
        }

        return $result;
    }

    /**
     * @return int the total number of errors caught in all tests
     */
    public function error_count()
    {
        $count = 0;

        foreach ($this->tests as $test) {
            $count += count($test->errors);
        }

        return $count;
    }

    /**
     * helper to deal with scoping fixtures. To store a fixture:
     *         $this->fixture('foo', 'bar');
     * to retrieve a fixture:
     *         $this->fixture('foo');
     *
     * I wish we didn't have to do this. In PHP 5.4 we may just be
     * able to bind the tests to an object and access fixtures via $this
     *
     * @param string $key the key to set or retrieve
     * @param mixed  $val the value to assign to the key. OPTIONAL
     *
     * @see setup()
     * @return mixed the value of the $key passed.
     */
    public function fixture($key, $val = null)
    {
        if (isset($val)) {
            $this->fixtures[$key] = $val;
        }

        return $this->fixtures[$key];
    }

    /**
     * removes all fixtures. This won't magically close connections or files, tho
     *
     * @see fixture()
     * @see teardown()
     */
    public function reset_fixtures()
    {
        $this->fixtures = array();
    }

    /**
     * assert that $a is like $b: `$a == $b`
     *
     * @param mixed  $a   the actual value
     * @param mixed  $b   the expected value
     * @param string $msg optional description of assertion
     */
    public function like($a, $b, $msg = null)
    {
        $this->current_test->assertions[] = new Assertion(
            __FUNCTION__,
            array($a, $b),
            ($a == $b),
            $msg,
            'Expected: ' . var_export($a, true) . ' == ' . var_export($b, true)
        );
    }

    /**
     * assert that $a is unlike $b: `$a != $b`
     *
     * @param mixed  $a   the actual value
     * @param mixed  $b   the expected value
     * @param string $msg optional description of assertion
     */
    public function unlike($a, $b, $msg = null)
    {
        $this->current_test->assertions[] = new Assertion(
            __FUNCTION__,
            array($a, $b),
            ($a != $b),
            $msg,
            'Expected: ' . var_export($a, true) . ' != ' . var_export($b, true)
        );
    }

    /**
     * assert that $a is strictly equal to $b: `$a === $b`
     *
     * @param mixed  $a   the actual value
     * @param mixed  $b   the expected value
     * @param string $msg optional description of assertion
     */
    public function eq($a, $b, $msg = null)
    {
        $this->current_test->assertions[] = new Assertion(
            __FUNCTION__,
            array($a, $b),
            ($a === $b),
            $msg,
            'Expected: ' . var_export($a, true) . ' === ' . var_export($b, true)
        );
    }

    /**
     * assert that $a is strictly not equal to $b. Uses `!==` for comparison
     *
     * @param mixed  $a   the actual value
     * @param mixed  $b   the expected value
     * @param string $msg optional description of assertion
     */
    public function ne($a, $b, $msg = null)
    {
        $this->current_test->assertions[] = new Assertion(
            __FUNCTION__,
            array($a, $b),
            ($a !== $b),
            $msg,
            'Expected: ' . var_export($a, true) . ' !== ' . var_export($b, true)
        );
    }

    /**
     * assert that $a is truthy: `(bool) $a === true`
     *
     * @param mixed  $a   the actual value
     * @param string $msg optional description of assertion
     */
    public function check($a, $msg = null)
    {
        $this->current_test->assertions[] = new Assertion(
            __FUNCTION__,
            array($a),
            (bool)$a,
            $msg,
            'Expected: (bool) ' . var_export($a, true) . ' === true'
        );
    }

    /**
     * assert that $haystack has is an array with a index like $needle,
     * or an object with a property named $needle.
     *
     * @param string       $needle   the index or property-name to test for
     * @param array|object $haystack the array or object to test
     * @param string       $msg      optional description of assertion
     */
    public function has($needle, $haystack, $msg = null)
    {
        $result = false;

        if (is_object($haystack)) {
            $result = property_exists($haystack, $needle);
        } elseif (is_array($haystack)) {
            $result = array_key_exists($needle, $haystack);
        }

        $this->current_test->assertions[] = new Assertion(
            __FUNCTION__,
            array($needle, $haystack),
            $result,
            $msg,
            'Expected: ' . var_export($haystack, true) . ' to contain ' . var_export($needle, true)
        );
    }

    /**
     * Force a failed assertion (or issue a warning)
     *
     * @param string $msg        optional description of assertion
     * @param bool   $is_warning set this to true, if this failure should be considered a warning only
     */
    public function fail($msg, $is_warning = false)
    {
        $assertion = new Assertion(
            __FUNCTION__,
            array(),
            false,
            $msg
        );

        $assertion->is_warning = $is_warning;

        $this->current_test->assertions[] = $assertion;
    }

    /**
     * Fail an assertion in an expected way (issue a warning)
     *
     * @param string $msg description of the reason for expected failure
     *
     * @see fail()
     */
    public function warn($msg)
    {
        $this->fail($msg, true);
    }

    /**
     * Assert an error being triggered, or an Exception being thrown, while executing a given function.
     *
     * @param int|string     $type            expected (integer) error-code, or the name of the expected Exception-type (string)
     * @param string|Closure $msg_or_function the message, if you wish to provide one - or an anonymous function
     * @param Closure|null   $function        an anonymous function; or null if $msg_or_function is a function
     */
    public function expect($type, $msg_or_function, Closure $function = null)
    {
        /**
         * @var Error $error
         */

        if ($msg_or_function instanceof Closure) {
            $function = $msg_or_function;
            $msg = 'expected Exception of type ' . $type;
        } else {
            $msg = $msg_or_function;
        }

        $assertion = new Assertion(
            __FUNCTION__,
            array($type),
            false, // assume failure
            $msg,
            'Expected: ' . (is_int($type) ? $this->error_labels[$type] : $type . ' Exception')
        );

        $assertion->expected_error = $type;

        $this->current_test->assertions[] = $assertion;

        // running the function has to be the last thing we do, as it may (should) trigger an error:

        try {
            $function();
        } catch (Exception $e) {
            if ($e instanceof HandledError) {
                if (is_int($type)) {
                    $assertion->result = ($e->errno & $type) > 0;
                    $error = end($this->current_test->errors);
                    $error->expected = true;
                }
            } else {
                if (is_string($type)) {
                    $assertion->result = $e instanceof $type;
                    $this->exception_handler($e, $assertion->result);
                }
            }
        }
    }
}
