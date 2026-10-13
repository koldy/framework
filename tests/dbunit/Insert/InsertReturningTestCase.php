<?php

declare(strict_types=1);

namespace Tests\DbUnit\Insert;

use Koldy\Db;
use Koldy\Db\Adapter\AbstractAdapter;
use Koldy\Db\Query\Select;
use Tests\DbUnit\Support\DbUnitTestCase;
use Tests\DbUnit\Fixtures\Item;
use Tests\DbUnit\Fixtures\ItemIdOnly;
use Tests\DbUnit\Fixtures\ItemNoReturning;
use Tests\DbUnit\Fixtures\ItemReturning;

/**
 * Behaviour of INSERT ... RETURNING through the Insert builder and the Model, executed against a real database.
 * One subclass per adapter supplies the connection key, the dialect-specific CREATE TABLE and whether the adapter is
 * expected to emit RETURNING at all (MySQL is not). Models use RETURNING only when they opt in (ItemReturning,
 * ItemIdOnly); the plain Item fixture keeps the default and must never send it.
 */
abstract class InsertReturningTestCase extends DbUnitTestCase
{

	protected const string TABLE = 'koldy_dbunit_items';

	/**
	 * Connection key from tests/dbunit/config.php
	 */
	abstract protected static function adapterKey(): string;

	/**
	 * CREATE TABLE for self::TABLE with: id (auto increment PK), name, created_at (DB default), active (DB default)
	 */
	abstract protected static function createTableSql(): string;

	/**
	 * Is this adapter expected to actually send RETURNING to the database?
	 */
	abstract protected function expectsReturning(): bool;

	protected function setUp(): void
	{
		$adapter = static::adapter();
		$adapter->query('DROP TABLE IF EXISTS ' . self::TABLE)->exec();
		$adapter->query(static::createTableSql())->exec();

		Item::setAdapterConnection(static::adapterKey());
	}

	protected function tearDown(): void
	{
		static::adapter()->query('DROP TABLE IF EXISTS ' . self::TABLE)->exec();
	}

	protected static function adapter(): AbstractAdapter
	{
		return Db::getAdapter(static::adapterKey());
	}

	/**
	 * Row as the database reports it, with keys sorted so it can be compared regardless of column order
	 */
	private static function sorted(array $row): array
	{
		ksort($row);
		return $row;
	}

	// ────────── Insert builder ──────────

	public function testReturningYieldsInsertedRowWithDatabaseDefaults(): void
	{
		$insert = static::adapter()->insert(self::TABLE, ['name' => 'Alice'])->returning();
		$insert->exec();

		if ($this->expectsReturning()) {
			$row = $insert->getReturnedRow();

			$this->assertIsArray($row);
			$this->assertStringContainsString('RETURNING *', $insert->getSQL());
			$this->assertSame('Alice', $row['name']);
			$this->assertArrayHasKey('id', $row);
			$this->assertArrayHasKey('created_at', $row);
			$this->assertNotNull($row['created_at'], 'DB default must be part of the returned row');
			$this->assertArrayHasKey('active', $row);
			$this->assertCount(1, $insert->getReturnedRows());
		} else {
			$this->assertStringNotContainsString('RETURNING', $insert->getSQL());
			$this->assertSame([], $insert->getReturnedRows());
			$this->assertNull($insert->getReturnedRow());
		}

		$this->assertSame(1, Item::count());
	}

	public function testMultiRowInsertReturnsEveryRow(): void
	{
		$insert = static::adapter()->insert(self::TABLE)->addRows([
			['name' => 'Alice'],
			['name' => 'Bob'],
			['name' => 'Carol'],
		])->returning('id', 'name');
		$insert->exec();

		if ($this->expectsReturning()) {
			$rows = $insert->getReturnedRows();

			$this->assertCount(3, $rows);
			$this->assertSame(['Alice', 'Bob', 'Carol'], array_column($rows, 'name'));
			$this->assertSame(['id', 'name'], array_keys($rows[0]));
		} else {
			$this->assertSame([], $insert->getReturnedRows());
		}

		$this->assertSame(3, Item::count());
	}

	public function testInsertSelectReturnsCopiedRows(): void
	{
		static::adapter()->insert(self::TABLE)->addRows([['name' => 'Alice'], ['name' => 'Bob']])->exec();

		$insert = static::adapter()->insert(self::TABLE)
			->field('name')
			->selectFrom((new Select(self::TABLE))->field('name')->orderBy('id'))
			->returning('name');
		$insert->exec();

		if ($this->expectsReturning()) {
			$this->assertSame(['Alice', 'Bob'], array_column($insert->getReturnedRows(), 'name'));
		} else {
			$this->assertSame([], $insert->getReturnedRows());
		}

		$this->assertSame(4, Item::count());
	}

	public function testReturnedRowsSurviveAnotherQueryOnTheSameAdapter(): void
	{
		$insert = static::adapter()->insert(self::TABLE, ['name' => 'Alice'])->returning();
		$insert->exec();

		// this replaces the PDO statement on the shared adapter; the rows must have been fetched already
		static::adapter()->query('SELECT COUNT(*) AS cnt FROM ' . self::TABLE)->fetchAll();

		if ($this->expectsReturning()) {
			$this->assertSame('Alice', $insert->getReturnedRow()['name'] ?? null);
		} else {
			$this->assertNull($insert->getReturnedRow());
		}
	}

	// ────────── Model::create() ──────────

	public function testModelDefaultDoesNotUseReturning(): void
	{
		// opt-in since 3.5.0: a model that doesn't set $returning behaves exactly as before, on every adapter
		$created = Item::create(['name' => 'Alice']);
		$this->assertSame(['id', 'name'], array_keys(self::sorted($created->getData())));
		$this->assertEquals(Item::fetchOne($created->id)?->id, $created->id);

		$saved = new Item(['name' => 'Bob']);
		$this->assertSame(0, $saved->save());
		$this->assertSame(['id', 'name'], array_keys(self::sorted($saved->getData())));
		$this->assertEquals(Item::fetchOne($saved->id)?->id, $saved->id);
	}

	public function testModelCreateHoldsTheRowAsStoredInDatabase(): void
	{
		$item = ItemReturning::create(['name' => 'Alice']);
		$fetched = ItemReturning::fetchOne($item->id);

		$this->assertNotNull($fetched);

		if ($this->expectsReturning()) {
			$this->assertSame(self::sorted($fetched->getData()), self::sorted($item->getData()),
				'create() must hold exactly what the database stored, including defaults and DB-typed values');
		} else {
			// legacy path: only the submitted values plus the primary key from lastInsertId()
			$this->assertEquals($fetched->id, $item->id);
			$this->assertSame(['id', 'name'], array_keys(self::sorted($item->getData())));
		}
	}

	public function testModelCreateWithExplicitReturningColumnsKeepsSubmittedValues(): void
	{
		$item = ItemIdOnly::create(['name' => 'Bob']);
		$fetched = ItemIdOnly::fetchOne($item->id);

		$this->assertNotNull($fetched);
		$this->assertEquals($fetched->id, $item->id);
		$this->assertSame('Bob', $item->name);
		$this->assertSame(['id', 'name'], array_keys(self::sorted($item->getData())), 'only the PK is added, defaults are not fetched');
	}

	public function testModelCreateWithReturningDisabledStillGetsPrimaryKey(): void
	{
		$item = ItemNoReturning::create(['name' => 'Carol']);
		$fetched = ItemNoReturning::fetchOne($item->id);

		$this->assertNotNull($fetched);
		$this->assertEquals($fetched->id, $item->id);
		$this->assertSame(['id', 'name'], array_keys(self::sorted($item->getData())));
	}

	public function testModelCreateInsideRolledBackTransactionLeavesNoRow(): void
	{
		ItemReturning::beginTransaction();
		$item = ItemReturning::create(['name' => 'Temporary']);
		$this->assertNotNull($item->id, 'RETURNING (or lastInsertId) works inside a transaction');
		ItemReturning::rollBack();

		$this->assertSame(0, ItemReturning::count());
	}

	// ────────── Model::save() ──────────

	public function testModelSaveInsertsAndBehavesLikeCreate(): void
	{
		$item = new ItemReturning(['name' => 'Dave']);

		$this->assertSame(0, $item->save(), 'save() returns 0 for an insert');
		$this->assertNotNull($item->id);

		$fetched = ItemReturning::fetchOne($item->id);
		$this->assertNotNull($fetched);

		if ($this->expectsReturning()) {
			$this->assertSame(self::sorted($fetched->getData()), self::sorted($item->getData()));
		} else {
			$this->assertEquals($fetched->id, $item->id);
		}

		$this->assertSame(-1, $item->save(), 'nothing changed after the insert, so save() must not touch the database');
	}

	public function testModelSaveUpdatesWithoutReloadingTheRow(): void
	{
		$item = ItemReturning::create(['name' => 'Eve']);
		$before = $item->getData();

		$item->name = 'Eve Updated';
		$this->assertSame(1, $item->save(), 'save() returns affected rows for an update');

		$expected = $before;
		$expected['name'] = 'Eve Updated';
		$this->assertSame($expected, $item->getData(), 'an UPDATE never re-reads the row');

		$this->assertSame('Eve Updated', ItemReturning::fetchOne($item->id)?->name);
	}

}
