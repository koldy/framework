<?php declare(strict_types=1);

namespace Tests\Fixtures\HttpRoute\BrokenControllers;

/**
 * Intentionally does NOT extend HttpController.
 * Used to test that the router throws ServerException for non-controller static matches.
 */
class NotAController
{

	public function get(): string
	{
		return 'should-not-reach';
	}

}

