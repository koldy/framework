<?php declare(strict_types=1);

namespace Tests\Fixtures\HttpRoute\StaticRoutes;

use Koldy\Route\HttpRoute\HttpController;

/**
 * @extends HttpController<array{}>
 */
class Index extends HttpController
{

	public function get(): string
	{
		return 'index-page';
	}

}

