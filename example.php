<?php

use FUnit\fu;

require __DIR__ . '/FUnit.php';

class ExampleTest extends fu
{
	public function setup()
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

	public function forced_errors_and_exceptions()
	{
		trigger_error('This was triggered inside a test', E_USER_ERROR);

		trigger_error('This was triggered inside a test', E_USER_NOTICE);

		throw new Exception('This was thrown inside a test');
	}
}

$test = new ExampleTest();

$test->run();

// this should output an empty array, because our fixtures will be gone
var_dump($test->fixtures);

#echo "<pre>"; var_dump($test->tests);
