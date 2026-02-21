<?php declare(strict_types=1);

namespace Tests\Fixtures\HttpRoute\BrokenControllers\Broken;

/**
 * Intentionally does NOT extend HttpController.
 * Used to test that the router throws ServerException for non-controller dynamic matches.
 */
class __
{

	public function get(): string
	{
		return 'should-not-reach';
	}

}

