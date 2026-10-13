<?php

declare(strict_types=1);

namespace Tests\DbUnit\Select;

/**
 * Select builder against a real MariaDB server (tests/dbunit/compose.yaml). MariaDB has no FULL JOIN.
 */
class MariaDBSelectTest extends SelectTestCase
{

	protected static function adapterKey(): string
	{
		return 'mariadb';
	}

	protected function supportsFullJoin(): bool
	{
		return false;
	}

}
