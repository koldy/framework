<?php declare(strict_types=1);

namespace Tests\Fixtures\HttpRoute\CompanyHierarchy\Companies;

use Koldy\Route\HttpRoute\HttpController;

/**
 * @extends HttpController<array{}>
 */
class Status extends HttpController
{

	public function get(): string
	{
		return 'companies-status';
	}

}

