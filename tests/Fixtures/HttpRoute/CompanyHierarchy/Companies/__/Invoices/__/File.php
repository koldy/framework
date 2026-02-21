<?php declare(strict_types=1);

namespace Tests\Fixtures\HttpRoute\CompanyHierarchy\Companies\__\Invoices\__;

use Koldy\Route\HttpRoute\HttpController;

/**
 * @extends HttpController<array{company_slug: string, invoice_id: string}>
 */
class File extends HttpController
{

	public function get(): string
	{
		$companySlug = $this->context['company_slug'] ?? 'unknown';
		$invoiceId = $this->context['invoice_id'] ?? 'unknown';
		return "file-for-invoice-{$invoiceId}-of-{$companySlug}";
	}

}

