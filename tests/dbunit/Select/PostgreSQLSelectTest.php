<?php

declare(strict_types=1);

namespace Tests\DbUnit\Select;

/**
 * Select builder against a real PostgreSQL server (tests/dbunit/compose.yaml)
 */
class PostgreSQLSelectTest extends SelectTestCase
{

	protected static function adapterKey(): string
	{
		return 'postgres';
	}

}
