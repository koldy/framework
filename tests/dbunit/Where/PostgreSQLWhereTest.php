<?php

declare(strict_types=1);

namespace Tests\DbUnit\Where;

/**
 * Where builder against a real PostgreSQL server (tests/dbunit/compose.yaml)
 */
class PostgreSQLWhereTest extends WhereTestCase
{

	protected static function adapterKey(): string
	{
		return 'postgres';
	}

}
