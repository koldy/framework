<?php

declare(strict_types=1);

namespace Tests\DbUnit\UpdateDelete;

/**
 * Update/Delete builders against a real PostgreSQL server (tests/dbunit/compose.yaml)
 */
class PostgreSQLUpdateDeleteTest extends UpdateDeleteTestCase
{

	protected static function adapterKey(): string
	{
		return 'postgres';
	}

}
