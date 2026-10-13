<?php

declare(strict_types=1);

namespace Tests\DbUnit\Migration;

/**
 * Migration Manager against in-memory SQLite. Everything routes through the single cached "koldy_migration" adapter
 * instance, so the :memory: database stays consistent between the tracking table and the migration DDL.
 */
class SqliteMigrationTest extends MigrationTestCase
{

	protected static function adapterKey(): string
	{
		return 'sqlite';
	}

}
