<?php

require './autoload.php';

use mindplay\funit\Test;
use mindplay\funit\HtmlReport;

class ExampleException extends Exception
{}

class ExampleTest extends Test
{
    protected function setup()
    {
        $this->fixture('foobar', array('foo' => 'bar'));
    }

    public function this_is_a_test()
    {
        $this->ok(1, "the integer '1' is okay");
        $this->ok(0, "the integer '0' is not okay"); // this will fail!
    }

    public function another_test()
    {
        $this->equal(true, 1, "the integer '1' is truthy");
        $this->not_strict_equal(true, 1, "the integer '1' is NOT true");
        // access a fixture
        $foobar = $this->fixture('foobar');
        $this->equal($foobar['foo'], 'bar', "the fixture 'foobar' should have a key 'foo' equal to 'baz'");

        $fooarr = array('blam' => 'blaz');
        $this->has('blam', $fooarr, "\$fooarr has a key named 'blam'");


        $fooobj = new \StdClass;
        $fooobj->blam = 'blaz';
        $this->has('blam', $fooobj, "\$fooobj has a property named 'blam'");
    }

    public function forced_failure()
    {
        $this->fail('This is a forced fail');
    }

    public function expected_failure()
    {
        $this->expect_fail('This is a good place to describe a missing test');
    }

    public function forced_error()
    {
        trigger_error('this notice was triggered inside a test', E_USER_NOTICE);
        trigger_error('this error was triggered inside a test', E_USER_ERROR);
        // throwing an E_USER_ERROR will interrupt and fail this test
        trigger_error('This will never execute', E_USER_ERROR);
    }

    public function forced_exception()
    {
        throw new Exception('This was thrown inside a test');
        // throwing an Exception will interrupt and fail this test
        throw new Exception('This will never execute');
    }

    public function expected_error()
    {
        $this->fails(E_USER_ERROR, 'this function is expected to trigger an error', function() {
            trigger_error('this error is expected and will cause the error to succeed', E_USER_ERROR);
        });

        $this->fails(E_USER_NOTICE, 'this function was expected to trigger a notice', function() {
            trigger_error('this assertion will fail because a notice was expected', E_USER_ERROR);
        });

        $this->fails(E_USER_ERROR, 'this function was expected to trigger an error', function() {
            // this test fails because it does not trigger the expected error
        });
    }

    public function expected_exception()
    {
        $this->fails('ExampleException', 'this function is expected to throw an ExampleException', function() {
            throw new ExampleException();
        });

        $this->fails('ExampleException', 'this function was expected to throw an ExampleException', function() {
            // this test fails because it does not throw the expected Exception
        });
    }
}

$test = new ExampleTest();

#$test->run(new FUnit\ConsoleReport());

$test->run();
