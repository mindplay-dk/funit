<?php

namespace mindplay\funit;

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
        'RED'     => "31",
        'GREEN'   => "32",
        'YELLOW'  => "33",
        'BLUE'    => "34",
        'MAGENTA' => "35",
        'CYAN'    => "36",
        'WHITE'   => "37",
    );

    /**
     * Map of HTML colors.
     *
     * @var array map where color-name => color-code
     */
    protected $html_colors = array(
        'RED'     => "crimson",
        'GREEN'   => "limegreen",
        'YELLOW'  => "yellow",
        'BLUE'    => "blue",
        'MAGENTA' => "magenta",
        'CYAN'    => "cyan",
        'WHITE'   => "white",
    );

    public function __construct()
    {
        // detect console vs HTML mode:
        $this->console = (PHP_SAPI === 'cli');

        // detect support for colors on the console:
        $this->supports_colors = function_exists('posix_isatty') && posix_isatty(STDOUT);
    }

    public function render_header(TestSuite $fu)
    {
        if (! $this->console) {
            echo "<!DOCTYPE html>\n"
                . "<head><title>" . htmlspecialchars($fu->title) . " [FUnit]</title></head>\n"
                . "<body style=\"background:black; color:white;\">\n";
        }

        $this->out("UNIT TEST: " . $fu->title);
        $this->out("");
    }

    public function render_message($str, $debug = false)
    {
        if ($debug) {
            $this->debug_out($str);
        } else {
            $this->out($str);
        }
    }

    public function render_body(TestSuite $suite)
    {
        $this->out("");
        $this->out("RESULTS");
        $this->out("--------------------------------------------");

        foreach ($suite->tests as $test) {

            $assert_counts = $test->assertion_count;

            if ($test->passed) {
                $test_color = 'GREEN';
            } else {
                if ($assert_counts->warnings > 0) {
                    $test_color = 'YELLOW';
                } else {
                    $test_color = 'RED';
                }
            }

            $this->out(
                "TEST: " . $this->color("{$test->name} ({$assert_counts->passed}/{$assert_counts->count}):", $test_color)
            );

            foreach ($test->assertions as $assertion) {
                if ($assertion->is_warning) {
                    $assert_color = 'YELLOW';
                } else {
                    $assert_color = $assertion->result ? 'GREEN' : 'RED';
                }

                $status = $assertion->result ? 'PASS' : 'FAIL';

                $args = ($assertion->result === false) || ($this->debug === true)
                    ? $assertion->format_args()
                    : '...';

                $expected = ($assertion->is_warning ? ' (expected)' : '');

                $this->out(
                    " * {$status}: "
                    . $this->color(" {$assertion->func_name}({$args}) {$assertion->msg}{$expected}", $assert_color)
                );
            }

            if (count($test->errors) > 0) {
                foreach ($test->errors as $error) {
                    if (! $error->expected && $this->debug) {
                        $sep = "\n  -> ";
                        $source = $sep . implode($sep, $error->backtrace);
                    } else {
                        $source = "{$error->file}#{$error->line}";
                    }
                    $this->out(
                        ' * ' . $this->color(
                            $error->type . ": {$error->msg} in {$source}",
                            $error->expected ? 'CYAN' : 'BLUE'
                        )
                    );
                }
            }

            $this->out("");
        }

        $err_count = $suite->error_count();

        $err_color = ($err_count > 0)
            ? 'RED'
            : 'WHITE';

        $this->out(
            "ERRORS/EXCEPTIONS: "
            . $this->color($err_count, $err_color)
        );

        $totals = $suite->assertion_count;

        $this->out(
            "ASSERTIONS: "
            . $this->color("{$totals->count} total", 'WHITE') . " ("
            . $this->color("{$totals->passed} passed", 'GREEN') . ", "
            . $this->color("{$totals->failed} failed", 'RED') . ", "
            . $this->color("{$totals->warnings} warnings)", 'YELLOW')
        );

        $results = $suite->results;

        $this->out(
            "TESTS: {$results->run} run, "
            . $this->color("{$results->passed} passed", 'GREEN') . ", "
            . $this->color("{$results->total} total", 'WHITE')
        );
    }

    public function render_footer(TestSuite $fu)
    {
        if (! $this->console) {
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
        if ($this->debug) {
            $this->out($this->color($str, $this->debug_color));
        }
    }

    /**
     * Color-code a line for output on the terminal or as HTML.
     *
     * Colouring code loosely based on
     * http://www.zend.com//code/codex.php?ozid=1112&single=1
     *
     * @param string $text  the text to color-code
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