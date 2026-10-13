<?php

declare(strict_types=1);

namespace Tests\DbUnit\Insert;

use Koldy\Db\Adapter\MariaDB;
use Koldy\Db\Adapter\MySQL;

/**
 * MySQL has no INSERT ... RETURNING. This proves the clause is dropped silently and that the Model falls back to
 * lastInsertId() against a real MySQL server (tests/dbunit/compose.yaml).
 */
class MySQLInsertReturningTest extends InsertReturningTestCase
{

	protected static function adapterKey(): string
	{
		return 'mysql';
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
		return false;
	}

	public function testConnectionUsesPlainMySqlAdapter(): void
	{
		$this->assertInstanceOf(MySQL::class, static::adapter());
		$this->assertNotInstanceOf(MariaDB::class, static::adapter());
		$this->assertFalse(static::adapter()->supportsReturning());
	}

}
