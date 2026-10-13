<?php

declare(strict_types=1);

namespace Tests\DbUnit\ResultSet;

/**
 * ResultSet builder against a real MariaDB server (tests/dbunit/compose.yaml)
 */
class MariaDBResultSetTest extends ResultSetTestCase
{

	protected static function adapterKey(): string
	{
		return 'mariadb';
	}

}
