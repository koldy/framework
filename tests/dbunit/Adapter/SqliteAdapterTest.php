<?php

declare(strict_types=1);

namespace Tests\DbUnit\Adapter;

use Tests\DbUnit\Support\Schema;

/**
 * Adapter behaviour against in-memory SQLite, plus SQLite-only concerns: the custom ILIKE function registered on
 * connect, the :memory: path, and the version-gated RETURNING support. Because :memory: is discarded when the
 * connection closes, data does not survive a reconnect.
 */
class SqliteAdapterTest extends AdapterTestCase
{

	protected static function adapterKey(): string
	{
		return 'sqlite';
	}

	protected function dataSurvivesReconnect(): bool
	{
		return false;
	}

	public function testConfigUsesInMemoryDatabase(): void
	{
		$this->assertSame(':memory:', static::adapter()->getConfig()['path']);
	}

	public function testIlikeFunctionMatchesCaseInsensitively(): void
	{
		$adapter = static::adapter();
		$adapter->insert(Schema::ITEMS, ['name' => 'Apple'])->exec();
		$adapter->insert(Schema::ITEMS, ['name' => 'apple'])->exec();
		$adapter->insert(Schema::ITEMS, ['name' => 'Banana'])->exec();

		// SQLite registers ILIKE as a 2-argument function (mask, value), not as an infix operator
		$rows = $adapter->query('SELECT name FROM ' . Schema::ITEMS . ' WHERE ILIKE(:p, name)', ['p' => 'app%'])->fetchAll();

		$this->assertCount(2, $rows, 'ILIKE matches Apple and apple regardless of case');
	}

	public function testIlikeUnderscoreWildcardMatchesSingleChar(): void
	{
		$adapter = static::adapter();
		$adapter->insert(Schema::ITEMS, ['name' => 'cat'])->exec();
		$adapter->insert(Schema::ITEMS, ['name' => 'cot'])->exec();
		$adapter->insert(Schema::ITEMS, ['name' => 'coast'])->exec();

		$rows = $adapter->query('SELECT name FROM ' . Schema::ITEMS . ' WHERE ILIKE(:p, name)', ['p' => 'c_t'])->fetchAll();

		$this->assertCount(2, $rows, '_ matches exactly one character, so cat and cot match but coast does not');
	}

	public function testSupportsReturningReturnsBool(): void
	{
		$this->assertIsBool(static::adapter()->supportsReturning());
	}

}
