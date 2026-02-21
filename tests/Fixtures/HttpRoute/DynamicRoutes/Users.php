<?php declare(strict_types=1);

namespace Tests\Fixtures\HttpRoute\DynamicRoutes;

use Koldy\Route\HttpRoute\HttpController;

/**
 * @extends HttpController<array{}>
 */
class Users extends HttpController
{

	public function get(): string
	{
		return 'users-list';
	}

}

