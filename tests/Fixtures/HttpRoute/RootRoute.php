<?php declare(strict_types=1);

namespace Tests\Fixtures\HttpRoute;

use Koldy\Route\HttpRoute\HttpController;

/**
 * Root handler class for the "Tests\Fixtures\HttpRoute\RootRoute\" namespace.
 * The FQCN matches the namespace without trailing backslash, so this handles GET /.
 *
 * @extends HttpController<array{}>
 */
class RootRoute extends HttpController
{

	public function get(): string
	{
		return 'homepage';
	}

	public function post(): string
	{
		return 'homepage-post';
	}

}
