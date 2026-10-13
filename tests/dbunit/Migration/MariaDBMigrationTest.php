<?php

declare(strict_types=1);

namespace Tests\DbUnit\Migration;

/**
 * Migration Manager against a real MariaDB server (tests/dbunit/compose.yaml)
 */
class MariaDBMigrationTest extends MigrationTestCase
{

	protected static function adapterKey(): string
	{
		return 'mariadb';
	}

}
