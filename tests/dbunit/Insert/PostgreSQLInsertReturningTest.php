<?php

declare(strict_types=1);

namespace Tests\DbUnit\Insert;

use Koldy\Db\Adapter\PostgreSQL;
use Tests\DbUnit\Fixtures\Item;
use Tests\DbUnit\Fixtures\ItemIdOnly;
use Tests\DbUnit\Fixtures\ItemReturning;

/**
 * INSERT ... RETURNING against a real PostgreSQL server (tests/dbunit/compose.yaml)
 */
class PostgreSQLInsertReturningTest extends InsertReturningTestCase
{

	protected static function adapterKey(): string
	{
		return 'postgres';
	}

	protected static function createTableSql(): string
	{
		return 'CREATE TABLE ' . self::TABLE . ' (
			id SERIAL PRIMARY KEY,
			name VARCHAR(100) NOT NULL,
			created_at TIMESTAMP NOT NULL DEFAULT now(),
			active BOOLEAN NOT NULL DEFAULT true,
			payload BYTEA NULL
		)';
	}

	protected function expectsReturning(): bool
	{
		return true;
	}

	public function testDefaultModelKeepsStringPrimaryKeyFromLastInsertId(): void
	{
		// without opting in to $returning, the primary key comes from lastInsertId() as a string, same as before 3.5.0
		$item = Item::create(['name' => 'Alice']);

		$this->assertIsString($item->id);
	}

	public function testPrimaryKeyIsIntegerNotString(): void
	{
		$item = ItemReturning::create(['name' => 'Alice']);

		$this->assertIsInt($item->id);
	}

	public function testBooleanDefaultComesBackAsBoolAndIsAcceptedByIsBoolTrue(): void
	{
		$item = ItemReturning::create(['name' => 'Alice']);

		$this->assertTrue($item->active);
		$this->assertTrue(PostgreSQL::isBoolTrue($item->active));
	}

	public function testByteaComesBackAsStreamWithReturningAll(): void
	{
		$item = ItemReturning::create(['name' => 'Alice', 'payload' => 'binary']);

		// this is why Model::$returning exists: PDO hands bytea columns back as stream resources
		$this->assertIsResource($item->payload);
		$this->assertSame('binary', stream_get_contents($item->payload));
	}

	public function testByteaStaysSubmittedStringWithExplicitReturningColumns(): void
	{
		$item = ItemIdOnly::create(['name' => 'Alice', 'payload' => 'binary']);

		$this->assertSame('binary', $item->payload);
		$this->assertIsInt($item->id);
	}

}
