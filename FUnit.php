<?php

namespace FUnit;

use Closure;
use Exception;
use ReflectionClass;
use ReflectionMethod;

abstract class fu
{

	const VERSION = '0.3';

	/**
	 * debug mode
	 */
	public $DEBUG = false;
	public $DEBUG_COLOR = 'BLUE';

	public $PASS = 'PASS';
	public $FAIL = 'FAIL';

	/**
	 * $tests['name'] => array(
	 *                 'run'=>false,
	 *                 'pass'=>false,
	 *                 'test'=>null,
	 *                 'assertions'=>array('func_name'=>'foo', 'func_args'=array('a','b'), 'result'=>$result, 'msg'=>'blahblah'),
	 *                 'timing' => array('setup'=>ts, 'run'=>ts, 'teardown'=>ts, 'total'=ts),
	 */
	public $tests = array();

	public $current_test_name = null;

	public $fixtures = array();

	public $errors = array();

	protected $TERM_COLORS = array(
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
		foreach ($this->get_tests() as $method => $name) {
			$this->tests[$name] = array(
				'run' => false,
				'pass' => false,
				'test' => array($this, $method),
				'errors' => array(),
				'assertions' => array(),
			);
		}
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
			if ($method->getDeclaringClass()->isAbstract()) continue; // skip methods on abstract classes
			if (!$method->isPublic()) continue; // skip non-public methods
			if (in_array($method->name, array('setup', 'teardown'))) continue; // skip setup/teardown methods

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
	 * @see $this->run_test()
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
	 * @see $this->run()
	 */
	public function error_handler($num, $msg, $file, $line, $vars)
	{

		$datetime = date("Y-m-d H:i:s (T)");

		$types = array(
			E_ERROR => 'Error',
			E_WARNING => 'Warning',
			E_PARSE => 'Parsing Error',
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

		$type = $types[$num];

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
	 * @param array $edata ['datetime', 'num', 'type', 'msg', 'file', 'line']
	 * @see $this->errors
	 * @see $this->error_handler()
	 * @see $this->exception_handler()
	 */
	protected function add_error_data($edata)
	{

		$this->errors[] = $edata;

		if ($this->current_test_name) {
			$this->tests[$this->current_test_name]['errors'][] = $edata;
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
	 * @param string $line
	 * @param string $color default is 'DEFAULT'
	 * @see $this->TERM_COLORS
	 */
	protected function color($txt, $color = 'DEFAULT')
	{
		if (PHP_SAPI === 'cli') {
			// only color if output is a posix TTY
			if (function_exists('posix_isatty') && posix_isatty(STDOUT)) {
				$color = $this->TERM_COLORS[$color];
				$txt = chr(27) . "[0;{$color}m{$txt}" . chr(27) . "[00m";
			}
			// otherwise, don't touch $txt
		} else {
			$color = strtolower($color);
			$txt = "<span style=\"color: $color;\">" . htmlspecialchars($txt) . "</span>";
		}
		return $txt;
	}

	protected function out($str)
	{
		if (PHP_SAPI === 'cli') {
			echo $str . "\n";
		} else {
			echo "<tt>" . nl2br($str) . "</tt><br/>";
		}
	}

	protected function debug_out($str)
	{
		if (!$this->DEBUG) {
			return;
		}
		$this->out($this->color($str, $this->DEBUG_COLOR));
	}

	/**
	 * Output a report. Currently only supports text output
	 *
	 * @param string $format default is 'text'
	 * @see $this->report_text()
	 */
	public function report($format = 'text')
	{
		switch ($format) {
			case 'text':
			default:
				$this->report_text();
		}
	}

	/**
	 * Output a report as text
	 *
	 * Normally you would not call this method directly
	 *
	 * @see $this->report()
	 * @see $this->run()
	 */
	protected function report_text()
	{


		$total_assert_counts = $this->assert_counts();
		$test_counts = $this->test_counts();

		$this->out("RESULTS:");
		$this->out("--------------------------------------------");

		foreach ($this->tests as $name => $tdata) {

			$assert_counts = $this->assert_counts($name);
			if ($tdata['pass']) {
				$test_color = 'GREEN';
			} else {
				if (($assert_counts['total'] - $assert_counts['expected_fail']) == $assert_counts['pass']) {
					$test_color = 'YELLOW';
				} else {
					$test_color = 'RED';
				}
			}
			$this->out("TEST:" . $this->color(" {$name} ({$assert_counts['pass']}/{$assert_counts['total']}):", $test_color));

			foreach ($tdata['assertions'] as $ass) {
				if ($ass['expected_fail']) {
					$assert_color = 'YELLOW';
				} else {
					$assert_color = $ass['result'] == $this->PASS ? 'GREEN' : 'RED';
				}
				$this->out(" * "
					. $this->color("{$ass['result']}"
						. " {$ass['func_name']}("
						// @TODO we should coerce these into strings and output only on fail
						// . implode(', ', $ass['func_args'])
						. ") {$ass['msg']}" . ($ass['expected_fail'] ? ' (expected)' : ''), $assert_color));
			}
			if (count($tdata['errors']) > 0) {
				foreach ($tdata['errors'] as $error) {
					if ($this->DEBUG) {
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


		$err_count = count($tdata['errors']);
		$err_color = (count($tdata['errors']) > 0) ? 'RED' : 'WHITE';
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
	 * Normally you would not call this method directly
	 *
	 * @param string $func_name the name of the assertion function
	 * @param array $func_args the arguments for the assertion. Really just the $a (actual) and $b (expected)
	 * @param mixed $result this is expected to be truthy or falsy, and is converted into $this->PASS or $this->FAIL
	 * @param string $msg optional message describing the assertion
	 * @param bool $expected_fail optional expectation of the assertion to fail
	 * @see $this->ok()
	 * @see $this->equal()
	 * @see $this->not_equal()
	 * @see $this->strict_equal()
	 * @see $this->not_strict_equal()
	 */
	protected function add_assertion_result($func_name, $func_args, $result, $msg = null, $expected_fail = false)
	{
		$result = ($result) ? $this->PASS : $this->FAIL;
		$this->tests[$this->current_test_name]['assertions'][] = compact('func_name', 'func_args', 'result', 'msg', 'expected_fail');
	}

	/**
	 * Normally you would not call this method directly
	 *
	 * Run a single test of the passed $name
	 *
	 * @param string $name the name of the test to run
	 * @see $this->run_tests()
	 * @see $this->setup()
	 * @see $this->teardown()
	 * @see $this->test()
	 */
	protected function run_test($name)
	{
		$this->out("Running test '{$name}...'");
		$ts_start = microtime(true);

		// to associate the assertions in a test with the test,
		// we use this var to avoid the need to for globals
		$this->current_test_name = $name;
		$test = $this->tests[$name]['test'];

		// setup
		$this->before_run();
		$ts_setup = microtime(true);

		try {

			call_user_func($test);

		} catch (Exception $e) {

			$this->exception_handler($e);

		}
		$ts_run = microtime(true);

		// teardown
		$this->after_run();
		$ts_teardown = microtime(true);

		$this->current_test_name = null;
		$this->tests[$name]['run'] = true;
		$this->tests[$name]['timing'] = array(
			'setup' => $ts_setup - $ts_start,
			'run' => $ts_run - $ts_setup,
			'teardown' => $ts_teardown - $ts_run,
			'total' => $ts_teardown - $ts_start,
		);

		if (count($this->tests[$name]['errors']) > 0) {

			$this->tests[$name]['pass'] = false;

		} else {

			$assert_counts = $this->assert_counts($name);
			if ($assert_counts['pass'] === $assert_counts['total']) {
				$this->tests[$name]['pass'] = true;
			} else {
				$this->tests[$name]['pass'] = false;
			}
		}

		$this->debug_out("Timing: " . json_encode($this->tests[$name]['timing'])); // json is easy to read

		return $this->tests[$name];

	}

	/**
	 * Normally you would not call this method directly
	 *
	 * Run all of the registered tests
	 * @param string $filter optional test case name filter
	 * @see $this->run()
	 * @see $this->run_test()
	 */
	public function run_tests($filter = null)
	{
		foreach ($this->tests as $name => &$test) {
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
			if ($ass['result'] === $this->PASS) {
				$pass++;
			} elseif ($ass['result'] === $this->FAIL) {
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

		if ($test_name) {
			$assertions = $this->tests[$test_name]['assertions'];
			$rs = $this->test_asserts($test_name, $assertions);
			$total += $rs['total'];
			$pass += $rs['pass'];
			$fail += $rs['fail'];
			$expected_fail += $rs['expected_fail'];
		} else {
			foreach ($this->tests as $test_name => $tdata) {
				$assertions = $this->tests[$test_name]['assertions'];
				$rs = $this->test_asserts($test_name, $assertions);
				$total += $rs['total'];
				$pass += $rs['pass'];
				$fail += $rs['fail'];
				$expected_fail += $rs['expected_fail'];
			}
		}

		return compact('total', 'pass', 'fail', 'expected_fail');

	}

	/**
	 * Normally you would not call this method directly
	 *
	 * Retrieves stats about tests run. returns an array with the keys 'total', 'pass', 'run'
	 *
	 * @param string $test_name optional the name of the test about which to get assertion stats
	 * @return array has keys 'total', 'pass', 'run'
	 */
	protected function test_counts()
	{
		$total = count($this->tests);
		$run = 0;
		$pass = 0;

		foreach ($this->tests as $test_name => $tdata) {
			if ($tdata['pass']) {
				$pass++;
			}
			if ($tdata['run']) {
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
	 * @see $this->setup()
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
	 * @see $this->fixture()
	 * @see $this->teardown()
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
	 * @param string $msg optional description of assertion
	 * @param bool $exptected optionally expect this test to fail
	 */
	public function fail($msg = null, $expected = false)
	{
		$this->add_assertion_result(__FUNCTION__, array(), false, $msg, $expected);
		return false;
	}

	/**
	 * Fail an assertion in an expected way
	 * @param string $msg optional description of assertion
	 * @param bool $exptected optionally expect this test to fail
	 * @see $this->fail()
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
	 * @see $this->run_tests()
	 * @see $this->report()
	 */
	public function run($report = true, $filter = null)
	{
		// set handlers
		$old_error_handler = set_error_handler(array($this, 'error_handler'));

		$this->run_tests($filter);

		if ($report) {
			$this->report();
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
