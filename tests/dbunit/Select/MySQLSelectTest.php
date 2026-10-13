<?php

declare(strict_types=1);

namespace Tests\DbUnit\Select;

/**
 * Select builder against a real MySQL server (tests/dbunit/compose.yaml). MySQL has no FULL JOIN.
 */
class MySQLSelectTest extends SelectTestCase
{

	protected static function adapterKey(): string
	{
		return 'mysql';
	}

	protected function supportsFullJoin(): bool
	{
		return false;
	}

}
