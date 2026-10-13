<?php

declare(strict_types=1);

namespace Tests\DbUnit\UpdateDelete;

use Koldy\Db;
use Koldy\Db\Adapter\AbstractAdapter;
use Koldy\Db\Expr;
use Koldy\Db\Query\Delete;
use Koldy\Db\Query\Exception as QueryException;
use Koldy\Db\Query\Update;
use Tests\DbUnit\Support\DbUnitTestCase;
use Tests\DbUnit\Support\Schema;

/**
 * Behaviour of the Update and Delete query builders executed against a real database, on all four adapters. One
 * subclass per adapter supplies the connection key.
 *
 * Seeded data (created fresh in setUp), ids 1..5:
 *   1 Apple  (cat 1, 1.50), 2 Banana (cat 1, 0.75), 3 Carrot (cat 2, 0.50), 4 Damson (cat 2, 3.00), 5 Orphan (cat NULL, 9.99)
 */
abstract class UpdateDeleteTestCase extends DbUnitTestCase
{

	abstract protected static function adapterKey(): string;

	protected static function adapter(): AbstractAdapter
	{
		return Db::getAdapter(static::adapterKey());
	}

	protected function setUp(): void
	{
		$adapter = static::adapter();
		Schema::createItems($adapter);

		$adapter->insert(Schema::ITEMS, ['category_id' => 1, 'name' => 'Apple', 'price' => 1.50])->exec();
		$adapter->insert(Schema::ITEMS, ['category_id' => 1, 'name' => 'Banana', 'price' => 0.75])->exec();
		$adapter->insert(Schema::ITEMS, ['category_id' => 2, 'name' => 'Carrot', 'price' => 0.50])->exec();
		$adapter->insert(Schema::ITEMS, ['category_id' => 2, 'name' => 'Damson', 'price' => 3.00])->exec();
		$adapter->insert(Schema::ITEMS, ['category_id' => null, 'name' => 'Orphan', 'price' => 9.99])->exec();
	}

	protected function tearDown(): void
	{
		Schema::drop(static::adapter(), Schema::ITEMS);
	}

	private function countItems(): int
	{
		$row = static::adapter()->select(Schema::ITEMS)->field('COUNT(*)', 'total')->fetchFirst();
		return (int)$row['total'];
	}

	private function nameOf(int $id): ?string
	{
		$row = static::adapter()->select(Schema::ITEMS)->field('name')->where('id', $id)->fetchFirst();
		return $row === null ? null : $row['name'];
	}

	private function priceOf(int $id): float
	{
		$row = static::adapter()->select(Schema::ITEMS)->field('price')->where('id', $id)->fetchFirst();
		return (float)$row['price'];
	}

	// ────────── Update ──────────

	public function testUpdateSetWithWhere(): void
	{
		$update = static::adapter()->update(Schema::ITEMS)->set('name', 'Renamed')->where('id', 1);

		$this->assertSame(1, $update->rowCount(), 'rowCount() auto-executes and reports one affected row');
		$this->assertSame('Renamed', $this->nameOf(1));
	}

	public function testUpdateSetValuesMultipleColumns(): void
	{
		static::adapter()->update(Schema::ITEMS)
			->setValues(['name' => 'Multi', 'price' => 2.22])
			->where('id', 2)
			->exec();

		$this->assertSame('Multi', $this->nameOf(2));
		$this->assertEqualsWithDelta(2.22, $this->priceOf(2), 0.001);
	}

	public function testUpdateTableSetter(): void
	{
		$update = static::adapter()->update()->table(Schema::ITEMS)->set('name', 'Tabled')->where('id', 3);

		$this->assertSame(1, $update->exec()->rowCount());
		$this->assertSame('Tabled', $this->nameOf(3));
	}

	public function testUpdateWithExprValue(): void
	{
		static::adapter()->update(Schema::ITEMS)->set('name', new Expr("'ExprName'"))->where('id', 1)->exec();

		$this->assertSame('ExprName', $this->nameOf(1));
	}

	public function testIncrement(): void
	{
		static::adapter()->update(Schema::ITEMS)->increment('price', 2)->where('id', 1)->exec();

		$this->assertEqualsWithDelta(3.50, $this->priceOf(1), 0.001);
	}

	public function testDecrement(): void
	{
		static::adapter()->update(Schema::ITEMS)->decrement('price', 1)->where('id', 1)->exec();

		$this->assertEqualsWithDelta(0.50, $this->priceOf(1), 0.001);
	}

	public function testUpdateOrderByAppearsInSql(): void
	{
		// UPDATE ... ORDER BY is not portable (PostgreSQL rejects it), so only the generated SQL is asserted here
		$sql = static::adapter()->update(Schema::ITEMS)->set('price', 0)->orderBy('id', 'desc')->getSQL();

		$this->assertStringContainsString('ORDER BY', $sql);
		$this->assertStringContainsString('id DESC', $sql);
	}

	public function testUpdateWithoutWhereTouchesEveryRow(): void
	{
		$update = static::adapter()->update(Schema::ITEMS)->set('category_id', 99);

		$this->assertSame(5, $update->rowCount(), 'an UPDATE without WHERE affects the whole table');
	}

	public function testUpdateWithoutSetThrows(): void
	{
		$this->expectException(QueryException::class);
		(new Update(Schema::ITEMS, null, static::adapterKey()))->exec();
	}

	public function testUpdateWithoutTableThrows(): void
	{
		$this->expectException(QueryException::class);
		(new Update(null, ['name' => 'x'], static::adapterKey()))->exec();
	}

	// ────────── Delete ──────────

	public function testDeleteWithWhere(): void
	{
		$delete = static::adapter()->delete(Schema::ITEMS)->where('id', 1);

		$this->assertSame(1, $delete->rowCount());
		$this->assertNull($this->nameOf(1), 'the deleted row is gone');
		$this->assertSame(4, $this->countItems());
	}

	public function testDeleteFromSetter(): void
	{
		$delete = static::adapter()->delete()->from(Schema::ITEMS)->where('id', 2);

		$this->assertSame(1, $delete->rowCount());
		$this->assertSame(4, $this->countItems());
	}

	public function testDeleteWithoutWhereEmptiesTable(): void
	{
		$delete = static::adapter()->delete(Schema::ITEMS);

		$this->assertSame(5, $delete->rowCount(), 'a DELETE without WHERE removes every row');
		$this->assertSame(0, $this->countItems());
	}

	public function testDeleteWithoutTableThrows(): void
	{
		$this->expectException(QueryException::class);
		(new Delete(null, static::adapterKey()))->exec();
	}

}
