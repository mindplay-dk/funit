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
	 * @var string test method-name
	 */
	public $method;

	/**
	 * @var Error[] list of Errors caught while running this Test
	 */
	public $errors = array();

	/**
	 * @var Assertion[] list of Assertions performed while running this Test
	 */
	public $assertions = array();

	public $timing = array();

	public function __construct($name, $method)
	{
		$this->name = $name;
		$this->method = $method;
	}

	public function get_assertion_count()
	{
		return new AssertionCount($this->assertions);
	}
}

/**
 * This class represents an Assertion performed during a Test.
 */
class Assertion
{
	public $func_name;
	public $func_args;
	public $result;
	public $msg;
	public $description;
	public $expected_fail = false;

	public function __construct($func_name, $func_args, $result, $msg=null, $description=null)
	{
		$this->func_name = $func_name;
		$this->func_args = $func_args;
		$this->result = $result;
		$this->msg = $msg;
		$this->description = $description;
	}

	public function format_args()
	{
		$strings = array_map(function($var) {
			return var_export($var, true);
		}, $this->func_args);

		return strtr(implode(', ', $strings), array("\r"=>"", "\n"=>""));
	}
}

/**
 * This class represents a tally of Assertion results
 */
class AssertionCount
{
	public $count = 0;
	public $pass = 0;
	public $fail = 0;
	public $expected_fail = 0;

	/**
	 * @param Assertion $assertion
	 */
	public function tally(Assertion $assertion)
	{
		if ($assertion->result) {
			$this->pass++;
		} else {
			$this->fail++;
		}

		if ($assertion->expected_fail) {
			$this->expected_fail++;
		}

		$this->count++;
	}

	/**
	 * @param Assertion[] $assertions list of Assertions to be counted
	 */
	public function __construct($assertions=array())
	{
		foreach ($assertions as $assertion) {
			$this->tally($assertion);
		}
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

			if (isset($bt['file']) && $bt['file'] === __FILE__) {
				continue; // skip backtraces from this file
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

/**
 * This base-class defines the interface and properties of a Report-class
 */
abstract class Report
{
	/**
	 * @var bool toggle verbose report output (with backtraces)
	 */
	public $debug = false;

	abstract public function render_header(fu $fu);
	abstract public function render_message($msg, $debug=false);
	abstract public function render_body(fu $fu);
	abstract public function render_footer(fu $fu);
}

/**
 * This class implements the default Console-style report, which can be
 * run either from a browser or from a command-line console.
 */
class ConsoleReport extends Report
{
	/**
	 * @var string color for debug-messages
	 * @see debug_out()
	 */
	public $debug_color = 'BLUE';

	/**
	 * @var bool toggle console-based output (vs HTML output)
	 * @see __construct()
	 */
	public $console = false;

	/**
	 * @var bool enable colors in rendered report
	 */
	public $use_color = true;

	/**
	 * @var bool true if the console supports colors (POSIX TTY)
	 */
	private $supports_colors = false;

	/**
	 * Map of terminal colors.
	 *
	 * @var array map where color-name => color-code
	 */
	protected $term_colors = array(
		'RED' => "31",
		'GREEN' => "32",
		'YELLOW' => "33",
		'BLUE' => "34",
		'MAGENTA' => "35",
		'CYAN' => "36",
		'WHITE' => "37",
	);

	/**
	 * Map of HTML colors.
	 *
	 * @var array map where color-name => color-code
	 */
	protected $html_colors = array(
		'RED' => "crimson",
		'GREEN' => "limegreen",
		'YELLOW' => "yellow",
		'BLUE' => "blue",
		'MAGENTA' => "magenta",
		'CYAN' => "cyan",
		'WHITE' => "white",
	);

	public function __construct()
	{
		// detect console vs HTML mode:
		$this->console = (PHP_SAPI === 'cli');

		// detect support for colors on the console:
		$this->supports_colors = function_exists('posix_isatty') && posix_isatty(STDOUT);
	}

	public function render_header(fu $fu)
	{
		if (!$this->console) {
			echo "<!DOCTYPE html>\n"
				. "<head><title>" . htmlspecialchars($fu->title) . " [FUnit]</title></head>\n"
				. "<body style=\"background:black; color:white;\">\n";
		}

		$this->out("UNIT TEST: " . $fu->title);
		$this->out("");
	}

	public function render_message($str, $debug=false)
	{
		if ($debug) {
			$this->debug_out($str);
		} else {
			$this->out($str);
		}
	}

	public function render_body(fu $fu)
	{
		$this->out("");
		$this->out("RESULTS");
		$this->out("--------------------------------------------");

		foreach ($fu->tests as $test) {

			$assert_counts = $test->get_assertion_count();

			if ($test->pass) {
				$test_color = 'GREEN';
			} else {
				if (($assert_counts->count - $assert_counts->expected_fail) == $assert_counts->pass) {
					$test_color = 'YELLOW';
				} else {
					$test_color = 'RED';
				}
			}

			$this->out("TEST: " . $this->color("{$test->name} ({$assert_counts->pass}/{$assert_counts->count}):", $test_color));

			foreach ($test->assertions as $assertion) {
				if ($assertion->expected_fail) {
					$assert_color = 'YELLOW';
				} else {
					$assert_color = $assertion->result ? 'GREEN' : 'RED';
				}

				$status = $assertion->result ? 'PASS' : 'FAIL';

				$args = ($assertion->result === false) || ($this->debug === true)
					? $assertion->format_args()
					: '...';

				$expected = ($assertion->expected_fail ? ' (expected)' : '');

				$this->out(" * {$status}: "
					. $this->color(" {$assertion->func_name}({$args}) {$assertion->msg}{$expected}", $assert_color));
			}

			if (count($test->errors) > 0) {
				foreach ($test->errors as $error) {
					if ($this->debug) {
						$sep = "\n  -> ";
						$source = $sep . implode($sep, $error->backtrace);
					} else {
						$source = "{$error->file}#{$error->line}";
					}
					$this->out(
						' * ' . $this->color(
							strtoupper($error->type) . ": {$error->msg} in {$source}",
							'RED')
					);
				}
			}

			$this->out("");
		}

		$err_count = $fu->error_count();

		$err_color = ($err_count > 0)
			? 'RED'
			: 'WHITE';

		$this->out("ERRORS/EXCEPTIONS: "
			. $this->color($err_count, $err_color));

		$totals = $fu->assertion_counts();

		$this->out("ASSERTIONS: "
			. $this->color("{$totals->pass} pass", 'GREEN') . ", "
			. $this->color("{$totals->fail} fail", 'RED') . ", "
			. $this->color("{$totals->expected_fail} expected fail", 'YELLOW') . ", "
			. $this->color("{$totals->count} total", 'WHITE'));

		$test_counts = $fu->test_counts();

		$this->out("TESTS: {$test_counts['run']} run, "
			. $this->color("{$test_counts['pass']} pass", 'GREEN') . ", "
			. $this->color("{$test_counts['total']} total", 'WHITE'));
	}

	public function render_footer(fu $fu)
	{
		if (!$this->console) {
			echo "</body></html>";
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
	 * @param $str string message to output
	 */
	protected function debug_out($str)
	{
		if (!$this->debug) {
			return;
		}
		$this->out($this->color($str, $this->debug_color));
	}

	/**
	 * Color-code a line for output on the terminal or as HTML.
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
	protected function color($text, $color)
	{
		if ($this->console) {
			if ($this->use_color && $this->supports_colors) {
				$color = $this->term_colors[$color];
				return chr(27) . "[0;{$color}m{$text}" . chr(27) . "[00m";
			} else {
				return $text; // colors disabled, or not supported on this console
			}
		} else {
			if ($this->use_color) {
				$color = $this->html_colors[$color];
				return "<span style=\"color:$color;\">" . htmlspecialchars($text) . "</span>";
			} else {
				return htmlspecialchars($text);
			}
		}
	}
}

/**
 * HTML Report generator
 *
 * CSS borrowed from QUnit <http://qunitjs.com>
 */
class HtmlReport extends Report
{
	public function render_header(fu $fu)
	{
		ob_start();
	}

	public function render_message($str, $debug=false)
	{
		if ($debug) {
			echo "<span>".htmlspecialchars($str)."</span><br/>";
		} else {
			echo htmlspecialchars($str)."<br/>";
		}
	}

	public function render_body(fu $fu)
	{
		$messages = ob_get_clean();

		$test_counts = $fu->test_counts();

		$check = $test_counts['run'] === $test_counts['pass']
			? '&#10004;' // checkmark
			: '&#10008;'; // fail "X"

		echo "<!DOCTYPE html>\n"
			. "<head>\n"
			. "<title>{$check} " . htmlspecialchars($fu->title) . "</title>\n"
			. "<style type=\"text/css\">\n"
			. "body { font-family: Helvetica Neue Light, HelveticaNeue-Light, Helvetica Neue, Calibri, Helvetica, Arial, sans-serif; }\n"
			. "h1 { padding:0.5em 0 0.5em 1em; margin:0; color:#C2CCD1; background:#0D3349; font-size:1.5em; line-height:1em; font-weight:normal; border-radius:15px 15px 0 0; }\n"
			. "h2 { padding:0.5em 0 0.5em 2.5em; margin:0; background:#2B81AF; color:white; text-shadow:rgba(0, 0, 0, 0.5) 2px 2px 1px; font-size:small; }\n"
			. "tt { display:block; background:#EEE; color:#444; padding:0.5em 0 0.5em 2em; border-top:solid 5px #C6E746; }\n"
			. "tt span { color:#888; }\n"
			. "div.summary { margin:0; padding:0.5em 0.5em 0.5em 1.7em; color:#2B81AF; background:#D2E0E6; border-bottom:1px solid white; }\n"
			. "div.summary p { font-size:small; margin:2px 0; padding:0; }\n"
			. "div.report { background:#D2E0E6; color:#528CE0; border-radius: 0 0 15px 15px; margin:0; padding:0.4em 0.5em 0.4em 1.7em; }\n"
			. "div.report p { display:block; margin:0; color:#366097; font-size:small; font-weight:bold; cursor:pointer; }\n"
			. "div.report ol { display:block; list-style-position:inside; list-style-type:decimal; margin:0.2em 0 0.5em 0; padding: 0.5em; background-color:white; border-radius:15px; box-shadow:inset 0px 2px 13px #999; }\n"
			. "div.report ol li { margin:0.5em; padding:0.4em 0.5em 0.4em 0.5em; border-bottom:none; color:#5E740B; list-style-position:inside; font-size:small; }\n"
			. "div.report ol li.pass { border-left:25px solid #C6E746; }\n"
			. "div.report ol li.fail { border-left:25px solid #EE5757; }\n"
			. "div.report ol li.expected-fail { border-left:25px solid #EEE746; }\n"
			. "div.report ol li.error { list-style-type:none; border-left:25px solid #2B81AF; }\n"
			. "</style>\n"
			. "<script type=\"text/javascript\">\n"
			. "window.onload = function() {\n"
			. "  var tags = document.getElementsByTagName('p');\n"
			. "  for (var n in tags) {\n"
			. "    if (tags[n].className == 'toggle') {\n"
			. "      tags[n].onclick = function() {\n"
			. "        var css = this.nextSibling.style;\n"
			. "        css.display = css.display == 'none' ? 'block' : 'none';\n"
			. "        return false;\n"
			. "      }\n"
			. "    }\n"
			. "  }\n"
			. "}\n"
			. "</script>\n"
			. "</head>\n"
			. "<body>\n";

		echo "<h1>" . htmlspecialchars($fu->title) . "</h1>\n";

		echo "<tt>{$messages}</tt>\n";

		echo "<h2>php version ".phpversion()."; zend engine version ".zend_version()."</h2>\n";

		echo "<div class=\"summary\">\n";
		echo "<p>{$test_counts['run']} tests: {$test_counts['pass']} of {$test_counts['total']} passed</p>\n";

		$totals = $fu->assertion_counts();
		echo "<p>{$totals->count} assertions: {$totals->pass} passed, {$totals->fail} failed, {$totals->expected_fail} expected failed</p>\n";

		$err_count = $fu->error_count();
		echo "<p>{$err_count} errors/exceptions</p>\n";

		echo "</div>\n";

		echo "<div class=\"report\">\n";

		foreach ($fu->tests as $test) {
			$assert_counts = $test->get_assertion_count();

			$status = $test->pass ? 'PASS' : 'FAIL';

			echo "<p class=\"toggle\">{$status}: {$test->name} ({$assert_counts->pass} pass, {$assert_counts->fail} fail"
				. ($assert_counts->expected_fail === 0 ? '' : ", {$assert_counts->expected_fail} expected fail")
				. ")</p>";

			echo "<ol>\n";

			foreach ($test->assertions as $assertion) {
				$class = $assertion->expected_fail
					? 'expected-fail'
					: ($assertion->result ? 'pass' : 'fail');

				$args = ($assertion->result === false) || ($this->debug === true)
					? $assertion->format_args()
					: '...';

				$expected = ($assertion->expected_fail ? ' (expected)' : '');

				echo "<li class=\"{$class}\">{$assertion->func_name}({$args}) {$assertion->msg}{$expected}</li>\n";
			}

			foreach ($test->errors as $error) {
				if ($this->debug) {
					$sep = "<br/>&rarr; ";
					$source = $sep . implode($sep, $error->backtrace);
				} else {
					$source = "{$error->file}#{$error->line}";
				}

				echo "<li class=\"error\">" . strtoupper($error->type) . ": {$error->msg} in {$source}</li>";
			}

			echo "</ol>\n";
		}

		echo "</div>\n";
	}

	public function render_footer(fu $fu)
	{
		echo "</body></html>";
	}
}

abstract class fu
{
	const VERSION = '0.3';

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
	 * @var string a title for the unit-test
	 * @not defaults to the class-name of the concrete test-class
	 */
	public $title;

	/**
	 * @var Test[] individual tests configured for this test-suite.
	 */
	public $tests = array();

	/**
	 * @var Report the Report to render on run
	 */
	public $report = null;

	/**
	 * @var Test the Test that is currently being run
	 */
	public $current_test = null;

	public $fixtures = array();

	public function __construct()
	{
		// configure test title:
		$this->title = get_class($this);

		// configure tests:
		$this->tests = $this->get_tests();

		// configure default report:
		$this->report = new ConsoleReport();
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
	 * @return Test[] list of detected Tests
	 */
	protected function get_tests()
	{
		/**
		 * @var ReflectionMethod $method
		 * @var Test[] $tests
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

			if (!$method->isPublic()) {
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
	 * @see run_test()
	 */
	protected function exception_handler($e)
	{
		$error = new Error(
			0,
			get_class($e),
			$e->getMessage(),
			$e->getFile(),
			$e->getLine()
		);

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
	 * @note Normally you would not call this method directly
	 * @see run_tests()
	 * @see setup()
	 * @see teardown()
	 */
	protected function run_test(Test $test)
	{
		$this->out("Running test '{$test->name}...'");

		$this->current_test = $test;

		// setup:
		$time_started = microtime(true);
		$this->setup();
		$time_after_setup = microtime(true);

		// run test:
		try {
			$this->{$test->method}();
		} catch (Exception $e) {
			$this->exception_handler($e);
		}

		$time_after_run = microtime(true);

		// clean up:
		$this->teardown();
		$this->reset_fixtures();
		$time_after_teardown = microtime(true);

		$this->current_test = null;
		$test->run = true;
		$test->timing = array(
			'setup' => $time_after_setup - $time_started,
			'run' => $time_after_run - $time_after_setup,
			'teardown' => $time_after_teardown - $time_after_run,
			'total' => $time_after_teardown - $time_started,
		);

		if (count($test->errors) > 0) {
			$test->pass = false;
		} else {
			$count = $test->get_assertion_count();
			$test->pass = $count->pass === $count->count;
		}

		$this->debug_out("Timing: " . json_encode($test->timing)); // json is easy to read
	}

	/**
	 * Run all of the registered tests
	 *
	 * @note Normally you would not call this method directly
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

	/**
	 * Run the registered tests, and output a report
	 *
	 * @param boolean $report whether or not to output a report after tests run. Default true.
	 * @param string $filter optional test case name filter
	 * @see run_tests()
	 */
	public function run($report = true, $filter = null)
	{
		// render report header:
		if ($this->report) {
			$this->report->render_header($this);
		}

		// set handlers:
		$old_error_handler = set_error_handler(array($this, 'error_handler'));

		// run tests:
		$this->run_tests($filter);

		// restore handlers:
		if ($old_error_handler) {
			set_error_handler($old_error_handler);
		}

		// render report:
		if ($this->report) {
			$this->report->render_body($this);
			$this->report->render_footer($this);
		}
	}

	/**
	 * @return AssertionCount the sum total of all Assertions across all Tests
	 */
	public function assertion_counts()
	{
		$total = new AssertionCount();

		foreach ($this->tests as $test) {
			$assert_counts = $test->get_assertion_count();

			$total->pass += $assert_counts->pass;
			$total->fail += $assert_counts->fail;
			$total->expected_fail += $assert_counts->expected_fail;
			$total->count += $assert_counts->count;
		}

		return $total;
	}

	/**
	 * Retrieves stats about tests run. returns an array with the keys 'total', 'pass', 'run'
	 *
	 * @note Normally you would not call this method directly
	 *
	 * @return array has keys 'total', 'pass', 'run'
	 */
	public function test_counts()
	{
		$total = count($this->tests);
		$run = 0;
		$pass = 0;

		foreach ($this->tests as $test) {
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
	 * @return int the total number of errors caught in all tests
	 */
	public function error_count()
	{
		$count = 0;

		foreach ($this->tests as $test) {
			if (count($test->errors)) {
				$count++;
			}
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
		$this->current_test->assertions[] = new Assertion(
			__FUNCTION__,
			array($a, $b),
			($a == $b),
			$msg,
			'Expected: ' . var_export($a, true) . ' and ' . var_export($b, true) . ' to be loosely equal'
		);
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
		$this->current_test->assertions[] = new Assertion(
			__FUNCTION__,
			array($a, $b),
			($a != $b),
			$msg,
			'Expected: ' . var_export($a, true) . ' and ' . var_export($b, true) . ' to be unequal'
		);
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
		$this->current_test->assertions[] = new Assertion(
			__FUNCTION__,
			array($a, $b),
			($a === $b),
			$msg,
			'Expected: ' . var_export($a, true) . ' and ' . var_export($b, true) . ' to be strictly equal'
		);
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
		$this->current_test->assertions[] = new Assertion(
			__FUNCTION__,
			array($a, $b),
			($a !== $b),
			$msg,
			'Expected: ' . var_export($a, true) . ' and ' . var_export($b, true) . ' to be strictly unequal'
		);
	}

	/**
	 * assert that $a is truthy. Casts $a to boolean for result
	 *
	 * @param mixed $a the actual value
	 * @param string $msg optional description of assertion
	 */
	public function ok($a, $msg = null)
	{
		$this->current_test->assertions[] = new Assertion(
			__FUNCTION__,
			array($a),
			(bool)$a,
			$msg,
			'Expected: ' . var_export($a, true) . ' to be truthy'
		);
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
	 * Force a failed assertion
	 *
	 * @param string $msg optional description of assertion
	 * @param bool $expected_fail optionally expect this test to fail
	 */
	public function fail($msg = null, $expected_fail = false)
	{
		$assertion = new Assertion(
			__FUNCTION__,
			array(),
			false,
			$msg
		);

		$assertion->expected_fail = $expected_fail;

		$this->current_test->assertions[] = $assertion;
	}

	/**
	 * Fail an assertion in an expected way
	 *
	 * @param string $msg optional description of assertion
	 * @see fail()
	 */
	public function expect_fail($msg = null)
	{
		$this->fail($msg, true);
	}

	/**
	 * @TODO
	 */
	public function expect($int)
	{
	}
}
