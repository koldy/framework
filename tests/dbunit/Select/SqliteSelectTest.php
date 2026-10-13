<?php

declare(strict_types=1);

namespace Tests\DbUnit\Select;

use PDO;

/**
 * Select builder against in-memory SQLite. RIGHT and FULL JOIN are only available since SQLite 3.39.
 */
class SqliteSelectTest extends SelectTestCase
{

	protected static function adapterKey(): string
	{
		return 'sqlite';
	}

	private function supportsModernJoins(): bool
	{
		$version = (string)static::adapter()->getPDO()->getAttribute(PDO::ATTR_SERVER_VERSION);
		return version_compare($version, '3.39.0', '>=');
	}

	protected function supportsRightJoin(): bool
	{
		return $this->supportsModernJoins();
	}

	protected function supportsFullJoin(): bool
	{
		return $this->supportsModernJoins();
	}

}
