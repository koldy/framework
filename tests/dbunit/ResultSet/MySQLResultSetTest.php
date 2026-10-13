<?php

declare(strict_types=1);

namespace Tests\DbUnit\ResultSet;

/**
 * ResultSet builder against a real MySQL server (tests/dbunit/compose.yaml)
 */
class MySQLResultSetTest extends ResultSetTestCase
{

	protected static function adapterKey(): string
	{
		return 'mysql';
	}

}
