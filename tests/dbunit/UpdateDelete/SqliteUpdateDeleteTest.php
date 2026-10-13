<?php

declare(strict_types=1);

namespace Tests\DbUnit\UpdateDelete;

/**
 * Update/Delete builders against in-memory SQLite
 */
class SqliteUpdateDeleteTest extends UpdateDeleteTestCase
{

	protected static function adapterKey(): string
	{
		return 'sqlite';
	}

}
