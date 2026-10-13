<?php

declare(strict_types=1);

namespace Tests\DbUnit\Adapter;

use Koldy\Db\Adapter\MariaDB;
use Koldy\Db\Adapter\MySQL;

/**
 * Adapter behaviour against a real MariaDB server. MariaDB speaks the MySQL protocol (so it extends the MySQL adapter)
 * but, unlike MySQL, it supports INSERT ... RETURNING.
 */
class MariaDBAdapterTest extends AdapterTestCase
{

	protected static function adapterKey(): string
	{
		return 'mariadb';
	}

	protected function expectsSupportsReturning(): ?bool
	{
		return true;
	}

	public function testAdapterIsMariaDbWhichIsAMySqlAdapter(): void
	{
		$adapter = static::adapter();

		$this->assertInstanceOf(MySQL::class, $adapter, 'MariaDB reuses the MySQL connection handling');
		$this->assertInstanceOf(MariaDB::class, $adapter);
	}

	public function testSupportsReturning(): void
	{
		$this->assertTrue(static::adapter()->supportsReturning());
	}

}
