<?php declare(strict_types=1);

namespace Tests\Fixtures\HttpRoute\RootRoute;

use Koldy\Route\HttpRoute\HttpController;

/**
 * @extends HttpController<array{}>
 */
class About extends HttpController
{

	public function get(): string
	{
		return 'about-page';
	}

}
