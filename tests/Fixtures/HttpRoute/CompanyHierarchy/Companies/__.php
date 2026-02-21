<?php declare(strict_types=1);

namespace Tests\Fixtures\HttpRoute\CompanyHierarchy\Companies;

use Koldy\Route\HttpRoute\HttpController;

/**
 * @extends HttpController<array{company_slug: string}>
 */
class __ extends HttpController
{

	public function __construct(array $data)
	{
		parent::__construct($data);
		$this->context['company_slug'] = $this->segment;
	}

	public function get(): string
	{
		return 'company-detail-' . $this->segment;
	}

}

