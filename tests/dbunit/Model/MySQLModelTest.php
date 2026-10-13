<?php

declare(strict_types=1);

namespace Tests\DbUnit\Model;

/**
 * ActiveRecord Model against a real MySQL server (no RETURNING; create()/save() fall back to lastInsertId)
 */
class MySQLModelTest extends ModelTestCase
{

	protected static function adapterKey(): string
	{
		return 'mysql';
	}

	protected function expectsReturning(): bool
	{
		return false;
	}

}
