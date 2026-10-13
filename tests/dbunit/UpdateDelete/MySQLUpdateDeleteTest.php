<?php

declare(strict_types=1);

namespace Tests\DbUnit\UpdateDelete;

/**
 * Update/Delete builders against a real MySQL server (tests/dbunit/compose.yaml)
 */
class MySQLUpdateDeleteTest extends UpdateDeleteTestCase
{

	protected static function adapterKey(): string
	{
		return 'mysql';
	}

}
