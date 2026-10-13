<?php

declare(strict_types=1);

namespace Tests\DbUnit\ResultSet;

/**
 * ResultSet builder against a real PostgreSQL server (tests/dbunit/compose.yaml)
 */
class PostgreSQLResultSetTest extends ResultSetTestCase
{

	protected static function adapterKey(): string
	{
		return 'postgres';
	}

}
