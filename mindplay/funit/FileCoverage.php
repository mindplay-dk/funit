<?php

namespace mindplay\funit;

/**
 * This class represents the results of code-coverage analysis of an individual file.
 */
class FileCoverage
{
    /**
     * @param $path    string
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
     * @var string[] lines of code read from the covered file
     */
    public $lines;

    /**
     * @var bool[] map where line number => coverage status
     */
    protected $covered = array();

    /**
     * @param int $line
     */
    public function cover($line)
    {
        $this->covered[$line] = true;
    }

    /**
     * @return string[]
     */
    public function get_uncovered_lines()
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