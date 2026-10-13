<?php

declare(strict_types=1);

namespace Tests\DbUnit\Where;

/**
 * Where builder against a real MySQL server (tests/dbunit/compose.yaml)
 */
class MySQLWhereTest extends WhereTestCase
{

	protected static function adapterKey(): string
	{
		return 'mysql';
	}

}
