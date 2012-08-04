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
	public $name;

	public $run = false;

	public $pass = false;

	/**
	 * @var callable
	 */
	public $callback = null;

	/**
	 * @var Error[] list of Errors caught while running this Test
	 */
	public $errors = array();

	public $assertions = array();

	public $timing = array();

	public function __construct($name, $callback)
	{
		$this->name = $name;
		$this->callback = $callback;
	}
}

/**
 * This class represents a recorded error or exception, and backtrace information.
 */
class Error
{
	public $datetime;
	public $num;
	public $type;
	public $msg;
	public $file;
	public $line;

	/**
	 * @var string[] backtrace statements
	 */
	public $backtrace = array();

	public function __construct($num, $type, $msg, $file, $line)
	{
		$this->datetime = date("Y-m-d H:i:s (T)");

		$this->num = $num;
		$this->type = $type;
		$this->msg = $msg;
		$this->file = $file;
		$this->line = $line;
	}

	/**
	 * Add backtrace data to this Error
	 *
	 * @param array $backtrace a backtrace array, such as produced by debug_backtrace() and Exception::getTrace()
	 *
	 * @see debug_backtrace()
	 * @see Exception::getTrace()
	 */
	public function add_backtrace($backtrace)
	{
		foreach ($backtrace as $bt) {
			$str = '';

			if (isset($bt['class']) && __CLASS__ == $bt['class']) {
				continue; // don't bother backtracing from this class
			}

			if (isset($bt['file'])) {
				$str .= $bt['file'] . '#' . $bt['line'];
			}

			if (isset($bt['class']) && isset($bt['function'])) {
				$str .= " {$bt['class']}::{$bt['function']}(...)";
			} elseif (isset($bt['function'])) {
				$str .= " {$bt['function']}(...)";
			}

			$this->backtrace[] = $str;
		}
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

	/**
	 * @var Test
	 */
	public $current_test = null;

	public $fixtures = array();

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

		$this->tests = $this->get_tests();

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
	 * @return Test[] list of detected Tests
	 */
	public function get_tests()
	{
		/**
		 * @var ReflectionMethod $method
		 * @var Test[] $tests
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

			$tests[] = new Test(strtr($method->name, '_', ' '), array($this, $method->name));
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
		$error = new Error(
			0,
			get_class($e),
			$e->getMessage(),
			$e->getLine(),
			$e->getFile()
		);

		$error->add_backtrace($e->getTrace());

		$this->current_test->errors[] = $error;
	}


	/**
	 * custom error handler to catch errors triggered while running tests. this is
	 * registered at the start of $this->run() and deregistered at stop
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
			$line,
			$file
		);

		$error->add_backtrace(debug_backtrace());

		$this->current_test->errors[] = $error;
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
	 * @return string color-coded text (in terminal escape-codes) or HTML tags
	 *
	 * @see $term_colors
	 * @see $console
	 * @see $color
	 * @see $console_colors
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

			$assert_counts = $this->assert_counts($test);
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
						$bt = $sep . implode($sep, $error->backtrace);
					} else {
						$bt = "{$error->file}#{$error->line}";
					}
					$this->out(
						' * ' . $this->color(
							strtoupper($error->type) . ": {$error->msg} in {$bt}",
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
		$this->current_test->assertions[] = compact('func_name', 'func_args', 'result', 'msg', 'expected_fail');
	}

	/**
	 * Run a single test of the passed $name
	 *
	 * @param Test $test the Test to run
	 *
	 * @note Normally you would not call this method directly
	 * @see run_tests()
	 * @see setup()
	 * @see teardown()
	 */
	protected function run_test(Test $test)
	{
		$name = $test->name;

		$this->out("Running test '{$name}...'");
		$ts_start = microtime(true);

		// to associate the assertions in a test with the test,
		// we use this var to avoid the need to for globals
		$this->current_test = $test;
		$callback = $test->callback;

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

		$this->current_test = null;
		$test->run = true;
		$test->timing = array(
			'setup' => $ts_setup - $ts_start,
			'run' => $ts_run - $ts_setup,
			'teardown' => $ts_teardown - $ts_run,
			'total' => $ts_teardown - $ts_start,
		);

		if (count($test->errors) > 0) {

			$test->pass = false;

		} else {

			$assert_counts = $this->assert_counts($test);
			if ($assert_counts['pass'] === $assert_counts['total']) {
				$test->pass = true;
			} else {
				$test->pass = false;
			}
		}

		$this->debug_out("Timing: " . json_encode($test->timing)); // json is easy to read
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
				$this->run_test($test);
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
	 * @param Test|null $test a specific test; or null to count across all tests
	 *
	 * @return array has keys 'total', 'pass', 'fail', 'expected_fail'
	 */
	protected function assert_counts(Test $test=null)
	{
		$total = 0;
		$pass = 0;
		$fail = 0;
		$expected_fail = 0;

		$tests = $test === null
			? array($test)
			: $this->tests;

		foreach ($tests as $test) {
			$rs = $this->test_asserts($test->name, $test->assertions);
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
