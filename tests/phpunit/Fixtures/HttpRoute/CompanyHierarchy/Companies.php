<?php declare(strict_types=1);

namespace Tests\PhpUnit\Fixtures\HttpRoute\CompanyHierarchy;

use Koldy\Route\HttpRoute\HttpController;

/**
 * @extends HttpController<array{}>
 */
class Companies extends HttpController
{

	public function get(): string
	{
		return 'companies-list';
	}

}

