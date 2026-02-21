<?php declare(strict_types=1);

namespace Tests\Fixtures\HttpRoute\DynamicRoutes\Users\__;

use Koldy\Route\HttpRoute\HttpController;

/**
 * @extends HttpController<array{user_id: string}>
 */
class Invoices extends HttpController
{

	public function get(): string
	{
		$userId = $this->context['user_id'] ?? 'unknown';
		return "invoices-for-{$userId}";
	}

}

