<?php

declare(strict_types=1);

namespace Tests\DbUnit\Model;

/**
 * ActiveRecord Model against a real MariaDB server (supports RETURNING)
 */
class MariaDBModelTest extends ModelTestCase
{

	protected static function adapterKey(): string
	{
		return 'mariadb';
	}

	protected function expectsReturning(): bool
	{
		return true;
	}

}
