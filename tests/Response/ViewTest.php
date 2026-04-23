<?php

declare(strict_types=1);

namespace Tests\Response;

use Koldy\Application;
use Koldy\Response\Exception as ResponseException;
use Koldy\Response\View;
use PHPUnit\Framework\TestCase;

class ViewTest extends TestCase
{

	private static bool $appInitialized = false;

	public static function setUpBeforeClass(): void
	{
		if (!self::$appInitialized) {
			$_SERVER['SCRIPT_FILENAME'] = __FILE__;

			Application::useConfig([
				'site_url' => 'http://localhost',
				'env' => Application::TEST,
				'key' => 'ViewTestKey1234567',
				'timezone' => 'UTC',
				'paths' => [
					'application' => __DIR__ . '/../',
					'storage' => __DIR__ . '/../',
					'view' => __DIR__ . '/../Fixtures/Views/',
				],
			]);

			self::$appInitialized = true;
		}
	}

	// ── construction / factory ──

	public function testCreateReturnsViewInstance(): void
	{
		$view = View::create('hello');
		$this->assertInstanceOf(View::class, $view);
	}

	public function testSetViewIsFluent(): void
	{
		$view = View::create('hello');
		$this->assertSame($view, $view->setView('hello'));
	}

	// ── basic render ──

	public function testGetOutputRendersViewWithInstanceData(): void
	{
		$output = View::create('hello')
			->set('name', 'World')
			->getOutput();

		$this->assertSame('Hello, World!', $output);
	}

	public function testToStringEqualsGetOutput(): void
	{
		$view = View::create('hello')->set('name', 'Claude');
		$this->assertSame($view->getOutput(), (string) $view);
	}

	public function testSetDataReplacesAllData(): void
	{
		$view = View::create('hello')
			->set('name', 'First')
			->setData(['name' => 'Second']);

		$this->assertSame('Hello, Second!', $view->getOutput());
	}

	public function testAddDataMergesData(): void
	{
		$view = View::create('layout')
			->set('body', 'BODY')
			->addData(['title' => 'T']);

		$this->assertSame('[HDR:T]|BODY', $view->getOutput());
	}

	// ── missing view ──

	public function testGetOutputThrowsWhenViewFileMissing(): void
	{
		$this->expectException(ResponseException::class);
		$this->expectExceptionMessage('does-not-exist');
		View::create('does-not-exist')->getOutput();
	}

	// ── sub-view composition ──

	public function testLayoutComposesSubView(): void
	{
		$output = View::create('layout')
			->set('title', 'Welcome')
			->set('body', 'main content')
			->getOutput();

		$this->assertSame('[HDR:Welcome]|main content', $output);
	}

	public function testRenderPassesWithAsLocalVariables(): void
	{
		$output = View::create('uses-local')->getOutput();
		$this->assertSame('greeting=hi', $output);
	}

	public function testRenderThrowsOnNonStringKeyInWith(): void
	{
		$view = new class ('hello') extends View {
			public function callRender(string $view, array $with): string
			{
				return $this->render($view, $with);
			}
		};

		$this->expectException(ResponseException::class);
		$view->callRender('partial-with-locals', [0 => 'x']);
	}

	public function testRenderThrowsWhenSubViewMissing(): void
	{
		$view = new class ('hello') extends View {
			public function callRender(string $view): string
			{
				return $this->render($view);
			}
		};

		$this->expectException(ResponseException::class);
		$view->callRender('missing-sub-view');
	}

	// ── renderViewIf ──

	public function testRenderViewIfReturnsEmptyStringForMissingView(): void
	{
		$output = View::create('optional-missing')->getOutput();
		$this->assertSame('x', $output);
	}

	public function testRenderViewIfRendersExistingView(): void
	{
		$output = View::create('optional-present')
			->set('title', 'Z')
			->getOutput();

		$this->assertSame('before-[HDR:Z]-after', $output);
	}

	// ── exists ──

	public function testExistsReturnsTrueForExistingView(): void
	{
		$this->assertTrue(View::exists('hello'));
	}

	public function testExistsReturnsTrueForNestedView(): void
	{
		$this->assertTrue(View::exists('partials/header'));
	}

	public function testExistsReturnsFalseForMissingView(): void
	{
		$this->assertFalse(View::exists('does-not-exist'));
	}

	// ── renderViewInKeyIf ──

	public function testRenderViewInKeyIfRendersWhenKeySetAndViewExists(): void
	{
		$output = View::create('in-key')
			->set('partialName', 'partial-with-locals')
			->getOutput();

		$this->assertSame('greeting=yo', $output);
	}

	public function testRenderViewInKeyIfReturnsEmptyWhenKeyNotSet(): void
	{
		$output = View::create('in-key')->getOutput();
		$this->assertSame('', $output);
	}

	public function testRenderViewInKeyIfReturnsEmptyWhenViewMissing(): void
	{
		$output = View::create('in-key')
			->set('partialName', 'does-not-exist')
			->getOutput();

		$this->assertSame('', $output);
	}

	// ── setViewPath ──

	public function testSetViewPathReroutesResolution(): void
	{
		$view = View::create('different')
			->setViewPath(__DIR__ . '/../Fixtures/ViewsAlt/')
			->set('marker', 'ok');

		$this->assertSame('alt-root:ok', $view->getOutput());
	}

	public function testSetViewPathAppendsTrailingSlash(): void
	{
		$view = View::create('different')
			->setViewPath(__DIR__ . '/../Fixtures/ViewsAlt')
			->set('marker', 'no-slash');

		$this->assertSame('alt-root:no-slash', $view->getOutput());
	}

	// ── iteration + delegation ──

	public function testIterationDelegatesToSubViewPerElement(): void
	{
		$output = View::create('loop')
			->set('items', ['a', 'b', 'c'])
			->getOutput();

		$this->assertSame('(a)(b)(c)', $output);
	}

	// ── response lifecycle ──

	public function testInheritedHeaderAndStatusApi(): void
	{
		$view = View::create('hello')
			->set('name', 'x')
			->setHeader('X-Foo', 'bar')
			->statusCode(201);

		$this->assertTrue($view->hasHeader('X-Foo'));
		$this->assertContains('X-Foo: bar', $view->getHeaders());
		$this->assertSame(201, $view->getStatusCode());
	}

}
