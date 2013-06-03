<?php

namespace mindplay\funit;

/**
 * This class represents the results of code-coverage analysis of an individual file.
 *
 * @property-read string[] $uncovered_lines uncovered lines of source-code (indexed by line-numbers)
 */
class FileCoverage extends Accessors
{
    /**
     * @param string $path absolute path to covered source-code file
     */
    public function __construct($path)
    {
        $this->path = $path;

        $this->lines = array_map(
            function ($line) {
                return trim($line, "\r");
            },
            explode("\n", file_get_contents($path))
        );

        $this->covered = array_fill(0, count($this->lines), false);
    }

    /**
     * @var string path to the file
     */
    public $path;

    /**
     * @var string[] lines of code read from the covered file, indexed by line-numbers
     */
    public $lines;

    /**
     * @var bool[] map where line number => coverage status
     */
    protected $covered = array();

    /**
     * @param int $line line to flag as covered
     */
    public function cover($line)
    {
        $this->covered[$line] = true;
    }

    /**
     * @see $uncovered_lines
     */
    protected function get_uncovered_lines()
    {
        $uncovered = array();

        foreach ($this->covered as $line => $covered) {
            if (! $covered) {
                $uncovered[$line] = $this->lines[$line];
            }
        }

        return $uncovered;
    }
}