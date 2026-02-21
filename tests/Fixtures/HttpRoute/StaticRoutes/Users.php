<?php declare(strict_types=1);

namespace Tests\Fixtures\HttpRoute\StaticRoutes;

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

	public function post(): string
	{
		return 'user-created';
	}

}

