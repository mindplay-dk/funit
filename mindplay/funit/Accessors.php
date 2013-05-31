<?php

namespace mindplay\funit;

use RuntimeException;

/**
 * Base class adding support for get/set-accessors
 */
abstract class Accessors
{
    public function __get($name)
    {
        $fn = "get_$name";

        if (false === method_exists($this, $fn)) {
            $class = get_class($this);

            throw new RuntimeException("undefined property {$class}::{$name} or accessor {$class}::{$fn}()");
        }

        return $this->$fn();
    }

    public function __set($name, $value)
    {
        $fn = "get_$name";

        if (false === method_exists($this, $fn)) {
            $class = get_class($this);

            throw new RuntimeException("undefined property {$class}::{$name} or accessor {$class}::{$fn}()");
        }

        return $this->$fn($value);
    }
}
