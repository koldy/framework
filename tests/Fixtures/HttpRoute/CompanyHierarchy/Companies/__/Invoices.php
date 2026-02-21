<?php declare(strict_types=1);

namespace Tests\Fixtures\HttpRoute\CompanyHierarchy\Companies\__;

use Koldy\Route\HttpRoute\HttpController;

/**
 * @extends HttpController<array{company_slug: string}>
 */
class Invoices extends HttpController
{

	public function get(): string
	{
		$companySlug = $this->context['company_slug'] ?? 'unknown';
		return "invoices-for-{$companySlug}";
	}

}

