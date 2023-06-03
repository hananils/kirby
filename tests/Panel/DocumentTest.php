<?php

namespace Kirby\Panel;

use Kirby\Cms\App;
use Kirby\Filesystem\Dir;
use Kirby\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Kirby\Panel\Document
 */
class DocumentTest extends TestCase
{
	protected $app;
	protected $tmp = __DIR__ . '/tmp';

	public function setUp(): void
	{
		$this->app = new App([
			'roots' => [
				'index' => $this->tmp,
			]
		]);

		Dir::make($this->tmp);
	}

	public function tearDown(): void
	{
		// clear session file first
		$this->app->session()->destroy();

		Dir::remove($this->tmp);

		// clear fake json requests
		$_GET = [];

		// clean up $_SERVER
		unset($_SERVER['SERVER_SOFTWARE']);
	}


	/**
	 * @covers ::response
	 */
	public function testResponse(): void
	{
		// create panel dist files first to avoid redirect
		(new Assets())->link();

		// get panel response
		$response = Document::response([
			'test' => 'Test'
		]);

		$this->assertInstanceOf(Response::class, $response);
		$this->assertSame(200, $response->code());
		$this->assertSame('text/html', $response->type());
		$this->assertSame('UTF-8', $response->charset());
		$this->assertNotNull($response->body());
	}
}
