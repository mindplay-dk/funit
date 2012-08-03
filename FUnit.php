<?php

namespace FUnit;

use Closure;
use Exception;
use ReflectionClass;
use ReflectionMethod;

/**
 * This class represents the state of an individual test.
 */
class Test
{
	public $run = false;

	public $pass = false;

	/**
	 * @var callable
	 */
	public $test = null;

	public $errors = array();

	public $assertions = array();

	public $timing = array();

	public function __construct($test)
	{
		$this->test = $test;
	}
}

abstract class fu
{
	const VERSION = '0.3';

	/**
	 * @var bool toggle verbose report output (with backtraces)
	 */
	public $debug = false;

	/**
	 * @var string color for debug-messages
	 * @see debug_out()
	 */
	public $debug_color = 'BLUE';

	/**
	 * @var string displayed when a test passes
	 */
	public $pass = 'PASS';

	/**
	 * @var string displayed when a test fails
	 */
	public $fail = 'FAIL';

	/**
	 * @var array map where error-code => display-name
	 */
	public $error_labels = array(
		E_ERROR => 'Error',
		E_WARNING => 'Warning',
		E_PARSE => 'Parser Error',
		E_NOTICE => 'Notice',
		E_CORE_ERROR => 'Core Error',
		E_CORE_WARNING => 'Core Warning',
		E_COMPILE_ERROR => 'Compile Error',
		E_COMPILE_WARNING => 'Compile Warning',
		E_USER_ERROR => 'User Error',
		E_USER_WARNING => 'User Warning',
		E_USER_NOTICE => 'User Notice',
		E_STRICT => 'Runtime Notice',
		E_RECOVERABLE_ERROR => 'Catchable Fatal Error'
	);

	/**
	 * @var bool toggle console-based output (vs HTML output)
	 * @see __construct()
	 */
	public $console = false;

	/**
	 * @var bool toggle colors in rendered report
	 */
	public $color = true;

	/**
	 * @var bool true if the console supports colors (POSIX TTY)
	 */
	private $console_colors = false;

	/**
	 * @var Test[] individual tests configured for this test-suite.
	 */
	public $tests = array();

	public $current_test_name = null;

	public $fixtures = array();

	public $errors = array();

	/**
	 * Map of terminal colors.
	 *
	 * @var array map where color-name => color-code
	 */
	protected $term_colors = array(
		'BLACK' => "30",
		'RED' => "31",
		'GREEN' => "32",
		'YELLOW' => "33",
		'BLUE' => "34",
		'MAGENTA' => "35",
		'CYAN' => "36",
		'WHITE' => "37",
		'DEFAULT' => "00",
	);

	public function __construct()
	{
		// configure tests:

		foreach ($this->get_tests() as $method => $name) {
			$this->tests[$name] = new Test(array($this, $method));
		}

		// detect console vs HTML mode:

		$this->console = PHP_SAPI === 'cli';

		// detecte support for colors on the console:

		$this->console_colors = function_exists('posix_isatty') && posix_isatty(STDOUT);
	}

	/**
	 * Override this to perform common initialization before running a test.
	 */
	public function setup()
	{
		// empty default implementation
	}

	/**
	 * Override this to perform clean-up after running a test.
	 */
	public function teardown()
	{
		// empty default implementation
	}

	/**
	 * Called in {@see run_test()} before running a test.
	 */
	private function before_run()
	{
		$this->setup();
	}

	/**
	 * Called in {@see run_test()} after running a test.
	 */
	private function after_run()
	{
		$this->teardown();
		$this->reset_fixtures();
	}

	/**
	 * Returns a list of the test-methods (and display-names) of the concrete test-class.
	 *
	 * @return array map, where method-name => display-name
	 */
	public function get_tests()
	{
		/**
		 * @var ReflectionMethod $method
		 */

		$class = new ReflectionClass(get_class($this));

		$tests = array();

		foreach ($class->getMethods() as $method) {
			if ($method->getDeclaringClass()->isAbstract()) {
				continue; // skip methods on abstract classes
			}

			if (!$method->isPublic()) {
				continue; // skip non-public methods
			}

			if (in_array($method->name, array('setup', 'teardown'))) {
				continue; // skip setup/teardown methods
			}

			$tests[$method->name] = strtr($method->name, '_', ' ');
		}

		return $tests;
	}

	/**
	 * custom exception handler, massaging the format into the same we use for Errors
	 *
	 * We don't actually use this as a proper exception handler, so we can continue execution.
	 *
	 * @param Exception $e
	 * @return array ['datetime', 'num', 'type', 'msg', 'file', 'line']
	 * @see run_test()
	 */
	protected function exception_handler($e)
	{
		$datetime = date("Y-m-d H:i:s (T)");
		$num = 0;
		$type = get_class($e);
		$msg = $e->getMessage();
		$file = $e->getFile();
		$line = $e->getLine();

		$edata = compact('datetime', 'num', 'type', 'msg', 'file', 'line');

		$this->add_error_data($edata);
	}


	/**
	 * custom error handler to catch errors triggered while running tests. this is
	 * registered at the start of $this->run() and deregistered at stop
	 *
	 * @see run()
	 */
	public function error_handler($num, $msg, $file, $line, $vars)
	{
		$datetime = date("Y-m-d H:i:s (T)");

		$type = $this->error_labels[$num];

		$backtrace = array();
		foreach (debug_backtrace() as $bt) {
			$trace = '';
			if (isset($bt['function']) && __FUNCTION__ == $bt['function'] && isset($bt['class']) && __CLASS__ == $bt['class']) {
				continue; // don't bother backtracing
			}
			if (isset($bt['file'])) {
				$trace .= $bt['file'] . '#' . $bt['line'];
			}
			if (isset($bt['class']) && isset($bt['function'])) {
				$trace .= " {$bt['class']}::{$bt['function']}(...)";
			} elseif (isset($bt['function'])) {
				$trace .= " {$bt['function']}(...)";
			}
			$backtrace[] = $trace;

		}

		$edata = compact('datetime', 'num', 'type', 'msg', 'file', 'line', 'backtrace');

		$this->add_error_data($edata);
	}

	/**
	 * adds error data to the main $errors var property and the current test's
	 * error array
	 *
	 * @param array $edata ['datetime', 'num', 'type', 'msg', 'file', 'line']
	 *
	 * @see errors
	 * @see error_handler()
	 * @see exception_handler()
	 */
	protected function add_error_data($edata)
	{
		$this->errors[] = $edata;

		if ($this->current_test_name) {
			$this->tests[$this->current_test_name]->errors[] = $edata;
		}
	}


	/**
	 * Format a line for printing. Detects
	 * if the script is being run from the command
	 * line or from a browser; also detects TTY for color (so pipes work).
	 *
	 * Colouring code loosely based on
	 * http://www.zend.com//code/codex.php?ozid=1112&single=1
	 *
	 * @param string $text the text to color-code
	 * @param string $color default is 'DEFAULT'
	 *
	 * @see $term_colors
	 */
	protected function color($text, $color = 'DEFAULT')
	{
		if ($this->console) {
			if ($this->color && $this->console_colors) {
				$color = $this->term_colors[$color];
				return chr(27) . "[0;{$color}m{$text}" . chr(27) . "[00m";
			} else {
				return $text; // colors disabled, or not supported on this console
			}
		} else {
			if ($this->color) {
				$color = strtolower($color);
				return "<span style=\"color: $color;\">" . htmlspecialchars($text) . "</span>";
			} else {
				return htmlspecialchars($text);
			}
		}
	}

	/**
	 * Output a string
	 *
	 * @param $str string to output
	 */
	protected function out($str)
	{
		if ($this->console) {
			echo $str . "\n";
		} else {
			echo "<tt>" . nl2br($str) . "</tt><br/>";
		}
	}

	/**
	 * Output a debug message - only if {@see $debug} is set to true.
	 *
	 * @param $str debug message to output
	 */
	protected function debug_out($str)
	{
		if (!$this->debug) {
			return;
		}
		$this->out($this->color($str, $this->debug_color));
	}

	/**
	 * Output a report as text
	 *
	 * Normally you would not call this method directly
	 *
	 * @see run()
	 */
	protected function default_report()
	{
		$total_assert_counts = $this->assert_counts();
		$test_counts = $this->test_counts();

		$this->out("RESULTS:");
		$this->out("--------------------------------------------");

		foreach ($this->tests as $name => $test) {

			$assert_counts = $this->assert_counts($name);
			if ($test->pass) {
				$test_color = 'GREEN';
			} else {
				if (($assert_counts['total'] - $assert_counts['expected_fail']) == $assert_counts['pass']) {
					$test_color = 'YELLOW';
				} else {
					$test_color = 'RED';
				}
			}
			$this->out("TEST:" . $this->color(" {$name} ({$assert_counts['pass']}/{$assert_counts['total']}):", $test_color));

			foreach ($test->assertions as $assertion) {
				if ($assertion['expected_fail']) {
					$assert_color = 'YELLOW';
				} else {
					$assert_color = $assertion['result'] == $this->pass ? 'GREEN' : 'RED';
				}
				$this->out(" * "
					. $this->color("{$assertion['result']}"
						. " {$assertion['func_name']}("
						// @TODO we should coerce these into strings and output only on fail
						// . implode(', ', $ass['func_args'])
						. ") {$assertion['msg']}" . ($assertion['expected_fail'] ? ' (expected)' : ''), $assert_color));
			}
			if (count($test->errors) > 0) {
				foreach ($test->errors as $error) {
					if ($this->debug) {
						$sep = "\n  -> ";
						$bt = $sep . implode($sep, $error['backtrace']);
					} else {
						$bt = "{$error['file']}#{$error['line']}";
					}
					$this->out(
						' * ' . $this->color(
							strtoupper($error['type']) . ": {$error['msg']} in {$bt}",
							'RED')
					);
				}
			}

			$this->out("");
		}


		$err_count = count($test->errors);

		$err_color = (count($test->errors) > 0)
			? 'RED'
			: 'WHITE';

		$this->out("ERRORS/EXCEPTIONS: "
			. $this->color($err_count, $err_color));

		$this->out("ASSERTIONS: "
			. $this->color("{$total_assert_counts['pass']} pass", 'GREEN') . ", "
			. $this->color("{$total_assert_counts['fail']} fail", 'RED') . ", "
			. $this->color("{$total_assert_counts['expected_fail']} expected fail", 'YELLOW') . ", "
			. $this->color("{$total_assert_counts['total']} total", 'WHITE'));

		$this->out("TESTS: {$test_counts['run']} run, "
			. $this->color("{$test_counts['pass']} pass", 'GREEN') . ", "
			. $this->color("{$test_counts['total']} total", 'WHITE'));
	}

	/**
	 * add the result of an assertion
	 *
	 * @param string $func_name the name of the assertion function
	 * @param array $func_args the arguments for the assertion. Really just the $a (actual) and $b (expected)
	 * @param mixed $result this is expected to be truthy or falsy, and is converted into $this->pass or $this->fail
	 * @param string $msg optional message describing the assertion
	 * @param bool $expected_fail optional expectation of the assertion to fail
	 *
	 * @note Normally you would not call this method directly
	 * @see ok()
	 * @see equal()
	 * @see not_equal()
	 * @see strict_equal()
	 * @see not_strict_equal()
	 */
	protected function add_assertion_result($func_name, $func_args, $result, $msg = null, $expected_fail = false)
	{
		$result = ($result) ? $this->pass : $this->fail;
		$this->tests[$this->current_test_name]->assertions[] = compact('func_name', 'func_args', 'result', 'msg', 'expected_fail');
	}

	/**
	 * Run a single test of the passed $name
	 *
	 * @param string $name the name of the test to run
	 *
	 * @note Normally you would not call this method directly
	 * @see run_tests()
	 * @see setup()
	 * @see teardown()
	 */
	protected function run_test($name)
	{
		$this->out("Running test '{$name}...'");
		$ts_start = microtime(true);

		// to associate the assertions in a test with the test,
		// we use this var to avoid the need to for globals
		$this->current_test_name = $name;
		$callback = $this->tests[$name]->test;

		// setup
		$this->before_run();
		$ts_setup = microtime(true);

		try {
			call_user_func($callback);

		} catch (Exception $e) {

			$this->exception_handler($e);

		}
		$ts_run = microtime(true);

		// teardown
		$this->after_run();
		$ts_teardown = microtime(true);

		$this->current_test_name = null;
		$this->tests[$name]->run = true;
		$this->tests[$name]->timing = array(
			'setup' => $ts_setup - $ts_start,
			'run' => $ts_run - $ts_setup,
			'teardown' => $ts_teardown - $ts_run,
			'total' => $ts_teardown - $ts_start,
		);

		if (count($this->tests[$name]->errors) > 0) {

			$this->tests[$name]->pass = false;

		} else {

			$assert_counts = $this->assert_counts($name);
			if ($assert_counts['pass'] === $assert_counts['total']) {
				$this->tests[$name]->pass = true;
			} else {
				$this->tests[$name]->pass = false;
			}
		}

		$this->debug_out("Timing: " . json_encode($this->tests[$name]->timing)); // json is easy to read
	}

	/**
	 * Normally you would not call this method directly
	 *
	 * Run all of the registered tests
	 *
	 * @param string $filter optional test case name filter
	 * @see run()
	 * @see run_test()
	 */
	public function run_tests($filter = null)
	{
		foreach ($this->tests as $name => $test) {
			if (null === $filter || (stripos($name, $filter) !== false)) {
				$this->run_test($name);
			}
		}
	}

	private function test_asserts($test_name, $assertions)
	{

		$total = 0;
		$pass = 0;
		$fail = 0;
		$expected_fail = 0;

		foreach ($assertions as $ass) {
			if ($ass['result'] === $this->pass) {
				$pass++;
			} elseif ($ass['result'] === $this->fail) {
				$fail++;
				if ($ass['expected_fail']) {
					$expected_fail++;
				}
			}
			$total++;
		}

		return compact('total', 'pass', 'fail', 'expected_fail');

	}

	/**
	 * Normally you would not call this method directly
	 *
	 * Retrieves stats about assertions run. returns an array with the keys 'total', 'pass', 'fail', 'expected_fail'
	 *
	 * If called without passing a test name, retrieves info about all assertions. Else just for the named test
	 *
	 * @param string $test_name optional the name of the test about which to get assertion stats
	 * @return array has keys 'total', 'pass', 'fail', 'expected_fail'
	 */
	protected function assert_counts($test_name = null)
	{
		$total = 0;
		$pass = 0;
		$fail = 0;
		$expected_fail = 0;

		$names = $test_name === null
			? array_keys($this->tests)
			: array($test_name);

		foreach ($names as $name) {
			$test = $this->tests[$name];
			$rs = $this->test_asserts($test_name, $test->assertions);
			$total += $rs['total'];
			$pass += $rs['pass'];
			$fail += $rs['fail'];
			$expected_fail += $rs['expected_fail'];
		}

		return compact('total', 'pass', 'fail', 'expected_fail');

	}

	/**
	 * Normally you would not call this method directly
	 *
	 * Retrieves stats about tests run. returns an array with the keys 'total', 'pass', 'run'
	 *
	 * @return array has keys 'total', 'pass', 'run'
	 */
	protected function test_counts()
	{
		$total = count($this->tests);
		$run = 0;
		$pass = 0;

		foreach ($this->tests as $test_name => $test) {
			if ($test->pass) {
				$pass++;
			}
			if ($test->run) {
				$run++;
			}
		}

		return compact('total', 'pass', 'run');
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
	 * @param mixed $val the value to assign to the key. OPTIONAL
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
	 * assert that $a is equal to $b. Uses `==` for comparison
	 *
	 * @param mixed $a the actual value
	 * @param mixed $b the expected value
	 * @param string $msg optional description of assertion
	 */
	public function equal($a, $b, $msg = null)
	{
		$rs = ($a == $b);
		$this->add_assertion_result(__FUNCTION__, array($a, $b), $rs, $msg);
		if (!$rs) {
			$this->debug_out('Expected: ' . var_export($a, true) . ' and ' . var_export($b, true) . ' to be loosely equal');
		}
		return $rs;
	}

	/**
	 * assert that $a is not equal to $b. Uses `!=` for comparison
	 *
	 * @param mixed $a the actual value
	 * @param mixed $b the expected value
	 * @param string $msg optional description of assertion
	 */
	public function not_equal($a, $b, $msg = null)
	{
		$rs = ($a != $b);
		$this->add_assertion_result(__FUNCTION__, array($a, $b), $rs, $msg);
		if (!$rs) {
			$this->debug_out('Expected: ' . var_export($a, true) . ' and ' . var_export($b, true) . ' to be unequal');
		}
		return $rs;
	}

	/**
	 * assert that $a is strictly equal to $b. Uses `===` for comparison
	 *
	 * @param mixed $a the actual value
	 * @param mixed $b the expected value
	 * @param string $msg optional description of assertion
	 */
	public function strict_equal($a, $b, $msg = null)
	{
		$rs = ($a === $b);
		$this->add_assertion_result(__FUNCTION__, array($a, $b), $rs, $msg);
		if (!$rs) {
			$this->debug_out('Expected: ' . var_export($a, true) . ' and ' . var_export($b, true) . ' to be strictly equal');
		}
		return $rs;
	}

	/**
	 * assert that $a is strictly not equal to $b. Uses `!==` for comparison
	 *
	 * @param mixed $a the actual value
	 * @param mixed $b the expected value
	 * @param string $msg optional description of assertion
	 */
	public function not_strict_equal($a, $b, $msg = null)
	{
		$rs = ($a !== $b);
		$this->add_assertion_result(__FUNCTION__, array($a, $b), $rs, $msg);
		if (!$rs) {
			$this->debug_out('Expected: ' . var_export($a, true) . ' and ' . var_export($b, true) . ' to be strictly unequal');
		}
		return $rs;
	}

	/**
	 * assert that $a is truthy. Casts $a to boolean for result
	 *
	 * @param mixed $a the actual value
	 * @param string $msg optional description of assertion
	 */
	public function ok($a, $msg = null)
	{
		$rs = (bool)$a;
		$this->add_assertion_result(__FUNCTION__, array($a), $rs, $msg);
		if (!$rs) {
			$this->debug_out('Expected: ' . var_export($a, true) . ' to be truthy');
		}
		return $rs;
	}

	/**
	 * assert that $haystack has a key or property named $needle. If $haystack
	 * is neither, returns false
	 *
	 * @param string $needle the key or property to look for
	 * @param array|object $haystack the array or object to test
	 * @param string $msg optional description of assertion
	 */
	public function has($needle, $haystack, $msg = null)
	{
		if (is_object($haystack)) {
			$rs = (bool)property_exists($haystack, $needle);
		} elseif (is_array($haystack)) {
			$rs = (bool)array_key_exists($needle, $haystack);
		} else {
			$rs = false;
		}

		$this->add_assertion_result(__FUNCTION__, array($needle, $haystack), $rs, $msg);
		if (!$rs) {
			$this->debug_out('Expected: ' . var_export($haystack, true) . ' to contain ' . var_export($needle, true));
		}
		return $rs;
	}

	/**
	 * Force a failed assertion
	 *
	 * @param string $msg optional description of assertion
	 * @param bool $expected optionally expect this test to fail
	 */
	public function fail($msg = null, $expected = false)
	{
		$this->add_assertion_result(__FUNCTION__, array(), false, $msg, $expected);
		return false;
	}

	/**
	 * Fail an assertion in an expected way
	 *
	 * @param string $msg optional description of assertion
	 * @see fail()
	 */
	public function expect_fail($msg = null)
	{
		return $this->fail($msg, true);
	}

	/**
	 * Run the registered tests, and output a report
	 *
	 * @param boolean $report whether or not to output a report after tests run. Default true.
	 * @param string $filter optional test case name filter
	 * @see run_tests()
	 */
	public function run($report = true, $filter = null)
	{
		// set handlers
		$old_error_handler = set_error_handler(array($this, 'error_handler'));

		$this->run_tests($filter);

		if ($report) {
			$this->default_report();
		}

		// restore handlers
		if ($old_error_handler) {
			set_error_handler($old_error_handler);
		}
	}

	/**
	 * @TODO
	 */
	public function expect($int)
	{
	}
}
