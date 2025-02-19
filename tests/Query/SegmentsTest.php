<?php

namespace Kirby\Query;

use Kirby\Exception\BadMethodCallException;
use Kirby\Exception\InvalidArgumentException;
use stdClass;

/**
 * @coversDefaultClass Kirby\Query\Segments
 */
class SegmentsTest extends \PHPUnit\Framework\TestCase
{
	/**
	 * @covers ::factory
	 */
	public function testFactory()
	{
		$segments = Segments::factory('a.b.c');
		$this->assertCount(5, $segments);

		$segments = Segments::factory('a().b(foo.bar).c(homer.simpson(2))');
		$this->assertCount(5, $segments);
		$this->assertSame('c', $segments->nth(4)->method);
		$this->assertCount(1, $segments->nth(2)->arguments);
		$this->assertSame(1, $segments->nth(2)->position);

		$segments = Segments::factory('user0.profiles1.mastodon');
		$this->assertCount(5, $segments);
		$this->assertSame(2, $segments->nth(4)->position);
	}

	public function providerParse(): array
	{
		return [
			[
				'foo.bar(homer.simpson)?.url',
				['foo', '.', 'bar(homer.simpson)', '?.', 'url']
			],
			[
				'user.check("gin", "tonic", user.array("gin", "tonic").args)',
				['user', '.', 'check("gin", "tonic", user.array("gin", "tonic").args)']
			],
			[
				'a().b(foo.bar)?.c(homer.simpson(2))',
				['a()', '.', 'b(foo.bar)', '?.', 'c(homer.simpson(2))']
			],
			[
				'foo.bar(() => foo.homer?.url).foo?.bar',
				['foo', '.', 'bar(() => foo.homer?.url)', '.', 'foo', '?.', 'bar']
			]
		];
	}

	/**
	 * @covers ::parse
	 * @dataProvider providerParse
	 */
	public function testParse(string $string, array $result)
	{
		$segments = Segments::parse($string);
		$this->assertSame($result, $segments);
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveNestedArray1Level()
	{
		$segments = Segments::factory('user.username');
		$data  = [
			'user' => [
				'username' => 'homer'
			]
		];

		$this->assertSame('homer', $segments->resolve($data));
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveNestedNumericKeys()
	{
		$segments = Segments::factory('user.0');
		$data  = [
			'user' => [
				'homer',
				'marge'
			]
		];

		$this->assertSame('homer', $segments->resolve($data));

		$segments = Segments::factory('user.1');
		$this->assertSame('marge', $segments->resolve($data));
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveNestedArrayWithNumericMethods()
	{
		$segments = Segments::factory('user0.profiles1.mastodon');
		$data  = [
			'user0' => [
				'profiles1' => [
					'mastodon' => '@homer'
				]
			]
		];

		$this->assertSame('@homer', $segments->resolve($data));
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveNestedArray2Levels()
	{
		$segments = Segments::factory('user.profiles.mastodon');
		$data  = [
			'user' => [
				'profiles' => [
					'mastodon' => '@homer'
				]
			]
		];

		$this->assertSame('@homer', $segments->resolve($data));
	}

	public function scalarProvider(): array
	{
		return [
			['test', 'string'],
			[1, 'integer'],
			[1.1, 'float'],
			[true, 'boolean'],
			[false, 'boolean'],
		];
	}

	/**
	 * @covers ::resolve
	 * @dataProvider scalarProvider
	 */
	public function testResolveWithArrayScalarValue($scalar)
	{
		$segments = Segments::factory('value');
		$data     = ['value' => $scalar];
		$this->assertSame($scalar, $segments->resolve($data));
	}

	/**
	 * @covers ::resolve
	 * @dataProvider scalarProvider
	 */
	public function testResolveWithArrayScalarValue2Level($scalar)
	{
		$segments = Segments::factory('parent.value');
		$data     =  [
			'parent' => [
				'value' => $scalar
			]
		];
		$this->assertSame($scalar, $segments->resolve($data));
	}

	/**
	 * @covers ::resolve
	 * @dataProvider scalarProvider
	 */
	public function testResolveWithArrayScalarValueError($scalar, $type)
	{
		$this->expectException(BadMethodCallException::class);
		$this->expectExceptionMessage('Access to method/property "method" on ' . $type);

		$segments = Segments::factory('value.method');
		$data     = ['value' => $scalar];
		$segments->resolve($data);
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveWithArrayNullValue()
	{
		$segments = Segments::factory('value');
		$data     = ['value' => null];
		$this->assertNull($segments->resolve($data));
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveWithArrayNullValueError()
	{
		$this->expectException(BadMethodCallException::class);
		$this->expectExceptionMessage('Access to method/property "method" on null');

		$segments = Segments::factory('value.method');
		$data     = ['value' => null];
		$segments->resolve($data);
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveWithArrayCallClosure()
	{
		$segments = Segments::factory('closure("test")');
		$data     = ['closure' => fn ($arg) => strtoupper($arg)];
		$this->assertSame('TEST', $segments->resolve($data));
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveWithArrayCallError()
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Cannot access array element "editor" with arguments');

		$segments = Segments::factory('editor("test")');
		$data     = ['editor' => new TestUser()];
		$segments->resolve($data);
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveWithArrayMissingKey1()
	{
		$this->expectException(BadMethodCallException::class);
		$this->expectExceptionMessage('Access to non-existing property "editor" on array');

		$segments = Segments::factory('editor');
		$segments->resolve();
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveWithArrayMissingKey2()
	{
		$this->expectException(BadMethodCallException::class);
		$this->expectExceptionMessage('Access to non-existing property "editor" on array');

		$segments = Segments::factory('editor.username');
		$segments->resolve();
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveWithObject1Level()
	{
		$segments = Segments::factory('user.username');
		$data     = ['user' => new TestUser()];
		$this->assertSame('homer', $segments->resolve($data));
	}

	/**
	 * @covers ::resolve
	 */
	public function tesResolvetWithObject2Level()
	{
		$segments = Segments::factory('user.profiles.mastodon');
		$data     = ['user' => new TestUser()];
		$this->assertSame('@homer', $segments->resolve($data));
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveWithObjectProperty()
	{
		$obj = new stdClass();
		$obj->test = 'testtest';
		$segments = Segments::factory('obj.test');
		$this->assertSame('testtest', $segments->resolve(compact('obj')));
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveWithObjectPropertyCallError()
	{
		$this->expectException(BadMethodCallException::class);
		$this->expectExceptionMessage('Access to non-existing method "test" on object');

		$obj = new stdClass();
		$obj->test = 'testtest';
		$segments = Segments::factory('obj.test(123)');
		$segments->resolve(compact('obj'));
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveWithObjectMethodWithInteger()
	{
		$segments = Segments::factory('user.age(12)');
		$data     = ['user' => new TestUser()];
		$this->assertSame(12, $segments->resolve($data));
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveWithObjectMethodWithBoolean()
	{
		// true
		$segments = Segments::factory('user.isYello(true)');
		$data     = ['user' => new TestUser()];
		$this->assertTrue($segments->resolve($data));

		// false
		$segments = Segments::factory('user.isYello(false)');
		$data     = ['user' => new TestUser()];
		$this->assertFalse($segments->resolve($data));
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveWithObjectMethodWithNull()
	{
		$segments = Segments::factory('user.brainDump(null)');
		$data     = ['user' => new TestUser()];
		$this->assertNull($segments->resolve($data));
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveWithObjectMethodWithString()
	{
		// double quotes
		$segments = Segments::factory('user.says("hello world")');
		$data     = ['user' => new TestUser()];
		$this->assertSame('hello world', $segments->resolve($data));

		// single quotes
		$segments = Segments::factory("user.says('hello world' )");
		$data     = ['user' => new TestUser()];
		$this->assertSame('hello world', $segments->resolve($data));
	}

	/**
	 * @covers ::resolve
	 */
	public function testREsolveWithObjectMethodWithEmptyString()
	{
		// double quotes
		$segments = Segments::factory('user.says("")');
		$data     = ['user' => new TestUser()];
		$this->assertSame('', $segments->resolve($data));

		// single quotes
		$segments = Segments::factory("user.says('' )");
		$data     = ['user' => new TestUser()];
		$this->assertSame('', $segments->resolve($data));
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveWithObjectMethodWithStringEscape()
	{
		// double quotes
		$segments = Segments::factory('user.says("hello \" world")');
		$data     = ['user' => new TestUser()];
		$this->assertSame('hello " world', $segments->resolve($data));

		// single quotes
		$segments = Segments::factory("user.says('hello \' world' )");
		$data     = ['user' => new TestUser()];
		$this->assertSame("hello ' world", $segments->resolve($data));
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveWithObjectMethodWithMultipleArguments()
	{
		$segments = Segments::factory('user.says("hello", "world")');
		$data     = ['user' => new TestUser()];
		$this->assertSame('hello : world', $segments->resolve($data));

		// with escaping
		$segments = Segments::factory('user.says("hello\"", "world")');
		$data     = ['user' => new TestUser()];
		$this->assertSame('hello" : world', $segments->resolve($data));

		// with mixed quotes
		$segments = Segments::factory('user.says(\'hello\\\'\', "world\"")');
		$data     = ['user' => new TestUser()];
		$this->assertSame('hello\' : world"', $segments->resolve($data));
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveWithObjectMethodWithMultipleArgumentsAndComma()
	{
		$segments = Segments::factory('user.says("hello,", "world")');
		$data     = ['user' => new TestUser()];
		$this->assertSame('hello, : world', $segments->resolve($data));

		// with escaping
		$segments = Segments::factory('user.says("hello,\"", "world")');
		$data     = ['user' => new TestUser()];
		$this->assertSame('hello," : world', $segments->resolve($data));
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveWithObjectMethodWithMultipleArgumentsAndDot()
	{
		$segments = Segments::factory('user.says("I like", "love.jpg")');
		$data     = ['user' => new TestUser()];
		$this->assertSame('I like : love.jpg', $segments->resolve($data));

		// with escaping
		$segments = Segments::factory('user.says("I \" like", "love.\"jpg")');
		$data     = ['user' => new TestUser()];
		$this->assertSame('I " like : love."jpg', $segments->resolve($data));
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveWithObjectMethodWithTrickyCharacters()
	{
		$segments = Segments::factory("user.likes(['(', ',', ']', '[', ')']).self.brainDump('hello')");
		$data     = ['user' => new TestUser()];
		$this->assertSame('hello', $segments->resolve($data));
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveWithObjectMethodWithArray()
	{
		$segments = Segments::factory('user.self.check("gin", "tonic", ["gin", "tonic", "cucumber"])');
		$data     = ['user' => new TestUser()];
		$this->assertTrue($segments->resolve($data));
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveWithObjectMethodWithObjectMethodAsParameter()
	{
		$segments = Segments::factory('user.self.check("gin", "tonic", user.drink)');
		$data     = ['user' => new TestUser()];
		$this->assertTrue($segments->resolve($data));
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveWithNestedMethodCall()
	{
		$segments = Segments::factory('user.check("gin", "tonic", user.array("gin", "tonic").args)');
		$data     = ['user' => new TestUser()];
		$this->assertTrue($segments->resolve($data));
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveWithObjectMethodWithObjectMethodAsParameterAndMoreLevels()
	{
		$segments = Segments::factory("user.likes([',']).likes(user.brainDump(['(', ',', ']', ')', '['])).self");
		$data     = ['user' => $user = new TestUser()];
		$this->assertSame($user, $segments->resolve($data));
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveWithObjectMissingMethod1()
	{
		$this->expectException(BadMethodCallException::class);
		$this->expectExceptionMessage('Access to non-existing method/property "username" on object');

		$segments = Segments::factory('user.username');
		$data     = ['user' => new stdClass()];
		$segments->resolve($data);
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveWithObjectMissingMethod2()
	{
		$this->expectException(BadMethodCallException::class);
		$this->expectExceptionMessage('Access to non-existing method "username" on object');

		$segments = Segments::factory('user.username(12)');
		$data     = ['user' => new stdClass()];
		$segments->resolve($data);
	}

	/**
	 * @covers ::resolve
	 */
	public function testResolveWithOptionalChaining()
	{
		$segments = Segments::factory('user?.says("hi")');
		$data     = ['user' => new TestUser()];
		$this->assertSame('hi', $segments->resolve($data));

		$segments = Segments::factory('user.nothing?.says("hi")');
		$data     = ['user' => new TestUser()];
		$this->assertNull($segments->resolve($data));

		$this->expectException(BadMethodCallException::class);
		$this->expectExceptionMessage('Access to method/property "says" on null');

		$segments = Segments::factory('user.nothing.says("hi")');
		$segments->resolve($data);
	}
}
