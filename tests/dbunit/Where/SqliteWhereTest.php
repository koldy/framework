<?php

declare(strict_types=1);

namespace Tests\DbUnit\Where;

/**
 * Where builder against in-memory SQLite
 */
class SqliteWhereTest extends WhereTestCase
{

	protected static function adapterKey(): string
	{
		return 'sqlite';
	}

}
