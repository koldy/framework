<?php

declare(strict_types=1);

namespace Tests\DbUnit\Where;

/**
 * Where builder against a real MariaDB server (tests/dbunit/compose.yaml)
 */
class MariaDBWhereTest extends WhereTestCase
{

	protected static function adapterKey(): string
	{
		return 'mariadb';
	}

}
