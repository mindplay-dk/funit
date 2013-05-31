<?php

namespace mindplay\funit;

/**
 * HTML Report generator
 *
 * CSS borrowed from QUnit <http://qunitjs.com>
 */
class HtmlReport extends Report
{
    public function render_header(Test $fu)
    {
        ob_start();
    }

    public function render_message($str, $debug = false)
    {
        if ($debug) {
            if ($this->debug) {
                echo "<span>" . htmlspecialchars($str) . "</span><br/>";
            }
        } else {
            echo htmlspecialchars($str) . "<br/>";
        }
    }

    public function render_body(Test $fu)
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
            . "div.report ol li.expected-error { list-style-type:none; border-left:25px solid #A9C2CF; }\n"
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

        echo "<tt>{$messages}\n";

        if ($fu->coverage) {
            echo '<br/><strong>Code Coverage</strong><br/>';
            foreach ($fu->coverage->get_results($fu) as $file) {
                $uncovered = $file->get_uncovered_lines();

                echo '* ' . $file->path . ': ' . count($uncovered) . ' of ' . (count(
                        $file->lines
                    )) . ' lines uncovered<br/>';

                /*
                if ($this->debug) {
                    foreach ($uncovered as $line => $code) {
                        echo sprintf('%5d', $line).htmlspecialchars($code).'<br/>';
                    }
                }
                */
            }
        }

        echo '</tt>';

        echo "<h2>php version " . phpversion() . "; zend engine version " . zend_version() . "</h2>\n";

        echo "<div class=\"summary\">\n";
        echo "<p>{$test_counts['run']} tests: {$test_counts['pass']} of {$test_counts['total']} passed</p>\n";

        $totals = $fu->assertion_counts();
        echo "<p>{$totals->count} assertions: {$totals->pass} passed, {$totals->fail} failed, {$totals->expected_fail} expected failed</p>\n";

        $err_count = $fu->error_count();
        echo "<p>{$err_count} errors/exceptions logged</p>\n";

        echo "</div>\n";

        echo "<div class=\"report\">\n";

        foreach ($fu->tests as $test) {
            $assert_counts = $test->get_assertion_count();

            $status = $test->pass ? '&#10004; PASS' : '&#10008; FAIL';

            echo "<p class=\"toggle\">{$status}: {$test->name} ({$assert_counts->pass} pass, {$assert_counts->fail} fail"
                . ($assert_counts->expected_fail === 0 ? '' : ", {$assert_counts->expected_fail} expected fail")
                . ")</p>";

            $display = $test->pass ? 'none' : 'block';

            echo "<ol style=\"display:{$display}\">\n";

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
                if (! $error->expected && $this->debug) {
                    $sep = "<br/>&rarr; ";
                    $source = $sep . implode($sep, $error->backtrace);
                } else {
                    $source = "{$error->file}#{$error->line}";
                }

                $cls = $error->expected ? 'expected-error' : 'error';

                echo "<li class=\"$cls\">" . $error->type . ": {$error->msg} in {$source}</li>";
            }

            echo "</ol>\n";
        }

        echo "</div>\n";
    }

    public function render_footer(Test $fu)
    {
        echo "</body></html>";
    }
}