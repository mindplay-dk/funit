<?php

# optional, lightweight auto-loader for the mindplay\funit namespace

spl_autoload_register(function($class) {
    if (strpos($class, 'mindplay\funit\\') === 0) {
        require __DIR__
            . DIRECTORY_SEPARATOR
            . strtr($class, '\\', DIRECTORY_SEPARATOR)
            . '.php';
    }
});
