<?php declare(strict_types=1);

namespace Tests\Fixtures\HttpRoute\DynamicRoutes\Users;

use Koldy\Route\HttpRoute\HttpController;

/**
 * @extends HttpController<array{user_id: string}>
 */
class __ extends HttpController
{

	public function __construct(array $data)
	{
		parent::__construct($data);
		$this->context['user_id'] = $this->segment;
	}

	public function get(): string
	{
		return 'user-detail-' . $this->segment;
	}

}

