<?php

declare(strict_types=1);

namespace Tests\DbUnit\Insert;

use Koldy\Db\Adapter\MariaDB;

/**
 * INSERT ... RETURNING against a real MariaDB server (tests/dbunit/compose.yaml). MariaDB is driven through
 * pdo_mysql with native prepared statements and unbuffered queries, so this proves the RETURNING result set is
 * consumed correctly on that path.
 */
class MariaDBInsertReturningTest extends InsertReturningTestCase
{

	protected static function adapterKey(): string
	{
		return 'mariadb';
	}

	protected static function createTableSql(): string
	{
		return 'CREATE TABLE ' . self::TABLE . ' (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
			name VARCHAR(100) NOT NULL,
			created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			active TINYINT(1) NOT NULL DEFAULT 1
		) ENGINE=InnoDB';
	}

	protected function expectsReturning(): bool
	{
		return true;
	}

	public function testConnectionUsesMariaDbAdapter(): void
	{
		$this->assertInstanceOf(MariaDB::class, static::adapter());
	}

}
