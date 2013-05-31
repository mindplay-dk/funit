# mindplay-funit

A lightweight test suite for PHP 5.3+
based on [FUnit](https://github.com/funkatron/FUnit) by [Ed Finkler](https://github.com/funkatron/).

## Features

* Simple to write tests and get output – start writing tests **fast**
* Short, straightforward syntax
* Command-line and browser-based reporting

## Usage

An [example test-suite](https://github.com/mindplay-dk/funit/blob/non-static/example.php)
demonstrates the API and usage.

## Installation

### Install with Composer

If you're using [Composer](https://github.com/composer/composer) to manage dependencies,
you can add the test-suite as a development-time dependency:

    {
        "require-dev": {
            "mindplay/funit": ">=1.0"
        }
    }

### Install source from GitHub

To install the source code:

    git clone git://github.com/mindplay-dk/funit.git

And include it in your scripts:

    require_once '/path/to/funit/autoload.php';

### Install source from zip/tarball

Alternatively, you can fetch a [tarball](https://github.com/mindplay/funit/tarball/master) or [zipball](https://github.com/mindplay/funit/zipball/master):

    $ curl https://github.com/mindplay-dk/funit/tarball/master | tar xzv
    (or)
    $ wget https://github.com/mindplay-dk/funit/tarball/master -O - | tar xzv

### Using a Class Loader

If you're using a class loader (e.g., [Symfony Class Loader](https://github.com/symfony/ClassLoader)) for [PSR-0](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md)-style class loading:

    $loader->registerNamespace('mindplay\funit', 'path/to/vendor/mindplay-funit');
