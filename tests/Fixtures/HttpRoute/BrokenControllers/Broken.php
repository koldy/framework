<?php declare(strict_types=1);

namespace Tests\Fixtures\HttpRoute\BrokenControllers;

use Koldy\Route\HttpRoute\HttpController;

/**
 * Valid controller — exists so the router descends into the Broken\ namespace.
 * The Broken\__ class intentionally does NOT extend HttpController, triggering ServerException.
 *
 * @extends HttpController<array{}>
 */
class Broken extends HttpController
{

	public function get(): string
	{
		return 'broken-list';
	}

}

