<?php

declare(strict_types=1);

namespace Tests\DbUnit\Adapter;

use Koldy\Db\Adapter\MariaDB;
use Koldy\Db\Adapter\MySQL;
use Tests\DbUnit\Support\Schema;

/**
 * Adapter behaviour against a real MySQL server, plus MySQL-only concerns: it does NOT support RETURNING and it must
 * resolve to the MySQL adapter (never MariaDB).
 */
class MySQLAdapterTest extends AdapterTestCase
{

	protected static function adapterKey(): string
	{
		return 'mysql';
	}

	protected function expectsSupportsReturning(): ?bool
	{
		return false;
	}

	public function testAdapterIsMySqlAndNotMariaDb(): void
	{
		$adapter = static::adapter();

		$this->assertInstanceOf(MySQL::class, $adapter);
		$this->assertNotInstanceOf(MariaDB::class, $adapter);
	}

	public function testDoesNotSupportReturning(): void
	{
		$this->assertFalse(static::adapter()->supportsReturning());
	}

	public function testDefaultCharsetRoundTripsUnicode(): void
	{
		// no 'charset' key in the test config, so the adapter falls back to its utf8 default
		$this->assertArrayNotHasKey('charset', static::adapter()->getConfig());

		static::adapter()->insert(Schema::ITEMS, ['name' => 'Zürich'])->exec();
		$rows = static::adapter()->query('SELECT name FROM ' . Schema::ITEMS)->fetchAll();

		$this->assertSame('Zürich', $rows[0]['name']);
	}

}
