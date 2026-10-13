<?php

declare(strict_types=1);

namespace Tests\DbUnit\Model;

/**
 * ActiveRecord Model against a real PostgreSQL server
 */
class PostgreSQLModelTest extends ModelTestCase
{

	protected static function adapterKey(): string
	{
		return 'postgres';
	}

	protected function expectsReturning(): bool
	{
		return true;
	}

}
