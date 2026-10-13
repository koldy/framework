<?php

declare(strict_types=1);

namespace Tests\DbUnit\Migration;

/**
 * Migration Manager against a real MySQL server (tests/dbunit/compose.yaml)
 */
class MySQLMigrationTest extends MigrationTestCase
{

	protected static function adapterKey(): string
	{
		return 'mysql';
	}

}
