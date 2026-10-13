<?php

declare(strict_types=1);

namespace Tests\DbUnit\Migration;

/**
 * Migration Manager against a real PostgreSQL server (tests/dbunit/compose.yaml)
 */
class PostgreSQLMigrationTest extends MigrationTestCase
{

	protected static function adapterKey(): string
	{
		return 'postgres';
	}

}
