<?php declare(strict_types=1);

namespace Tests\Fixtures\HttpRoute\CompanyHierarchy\Companies\__\Invoices;

use Koldy\Route\HttpRoute\HttpController;

/**
 * @extends HttpController<array{company_slug: string, invoice_id: string}>
 */
class __ extends HttpController
{

	public function __construct(array $data)
	{
		parent::__construct($data);
		$this->context['invoice_id'] = $this->segment;
	}

	public function get(): string
	{
		$companySlug = $this->context['company_slug'] ?? 'unknown';
		return "invoice-{$this->segment}-for-{$companySlug}";
	}

}

