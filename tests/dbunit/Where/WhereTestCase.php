<?php

declare(strict_types=1);

namespace Tests\DbUnit\Where;

use InvalidArgumentException;
use Koldy\Db;
use Koldy\Db\Adapter\AbstractAdapter;
use Koldy\Db\Expr;
use Koldy\Db\Query\Exception as QueryException;
use Koldy\Db\Query\Select;
use Koldy\Db\Where;
use Tests\DbUnit\Support\DbUnitTestCase;
use Tests\DbUnit\Support\Schema;

/**
 * Behaviour of the Where builder executed against a real database, on all four adapters. Where has no exec of its own,
 * so every operator family is exercised through Select and asserted on the resulting rows. One subclass per adapter
 * supplies the connection key.
 *
 * Seeded data (created fresh in setUp), ids 1..5:
 *   1 Apple  (cat 1, 1.50), 2 Banana (cat 1, 0.75), 3 Carrot (cat 2, 0.50), 4 Damson (cat 2, 3.00), 5 Orphan (cat NULL, 9.99)
 */
abstract class WhereTestCase extends DbUnitTestCase
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

	private function select(): Select
	{
		return static::adapter()->select(Schema::ITEMS);
	}

	/**
	 * Names of matched rows, ordered by id for a stable comparison
	 */
	private function names(Select $select): array
	{
		return array_column($select->orderBy('id')->fetchAll(), 'name');
	}

	// ────────── where / orWhere ──────────

	public function testWhereEquals(): void
	{
		$this->assertSame(['Apple', 'Banana'], $this->names($this->select()->where('category_id', 1)));
	}

	public function testWhereGreaterThan(): void
	{
		$this->assertSame(['Apple', 'Damson', 'Orphan'], $this->names($this->select()->where('price', '>', 1)));
	}

	public function testWhereLessThanOrEqual(): void
	{
		$this->assertSame(['Banana', 'Carrot'], $this->names($this->select()->where('price', '<=', 0.75)));
	}

	public function testWhereNotEqual(): void
	{
		$this->assertSame(['Apple', 'Banana', 'Carrot', 'Damson'], $this->names($this->select()->where('name', '!=', 'Orphan')));
	}

	public function testChainedWhereIsAnded(): void
	{
		$select = $this->select()->where('category_id', 2)->where('price', '>', 1);
		$this->assertSame(['Damson'], $this->names($select));
	}

	public function testOrWhereWidensResult(): void
	{
		$select = $this->select()->where('category_id', 1)->orWhere('name', 'Orphan');
		$this->assertSame(['Apple', 'Banana', 'Orphan'], $this->names($select));
	}

	// ────────── argument guards ──────────

	public function testWhereWithSingleStringArgumentThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->select()->where('name');
	}

	public function testWhereWithNullValueThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->select()->where('name', null);
	}

	public function testOrWhereWithSingleStringArgumentThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->select()->orWhere('name');
	}

	// ────────── NULL ──────────

	public function testWhereNull(): void
	{
		$this->assertSame(['Orphan'], $this->names($this->select()->whereNull('category_id')));
	}

	public function testWhereNotNull(): void
	{
		$this->assertSame(['Apple', 'Banana', 'Carrot', 'Damson'], $this->names($this->select()->whereNotNull('category_id')));
	}

	public function testOrWhereNull(): void
	{
		$select = $this->select()->where('category_id', 2)->orWhereNull('category_id');
		$this->assertSame(['Carrot', 'Damson', 'Orphan'], $this->names($select));
	}

	public function testOrWhereNotNull(): void
	{
		// category_id = 1 OR category_id IS NOT NULL => everything that has a category
		$select = $this->select()->where('category_id', 1)->orWhereNotNull('category_id');
		$this->assertSame(['Apple', 'Banana', 'Carrot', 'Damson'], $this->names($select));
	}

	// ────────── BETWEEN ──────────

	public function testWhereBetween(): void
	{
		$this->assertSame(['Apple', 'Damson'], $this->names($this->select()->whereBetween('price', 1, 4)));
	}

	public function testWhereNotBetween(): void
	{
		$this->assertSame(['Banana', 'Carrot', 'Orphan'], $this->names($this->select()->whereNotBetween('price', 1, 4)));
	}

	public function testOrWhereBetween(): void
	{
		$select = $this->select()->where('name', 'Orphan')->orWhereBetween('price', 1, 4);
		$this->assertSame(['Apple', 'Damson', 'Orphan'], $this->names($select));
	}

	public function testWhereBetweenWithExprEndpoint(): void
	{
		// BETWEEN 0 AND 1, with the lower bound provided as a raw SQL expression
		$this->assertSame(['Banana', 'Carrot'], $this->names($this->select()->whereBetween('price', new Expr('0'), 1)));
	}

	// ────────── IN ──────────

	public function testWhereIn(): void
	{
		$this->assertSame(['Apple', 'Carrot', 'Orphan'], $this->names($this->select()->whereIn('id', [1, 3, 5])));
	}

	public function testWhereNotIn(): void
	{
		$this->assertSame(['Banana', 'Damson'], $this->names($this->select()->whereNotIn('id', [1, 3, 5])));
	}

	public function testOrWhereIn(): void
	{
		$select = $this->select()->where('category_id', 1)->orWhereIn('name', ['Damson']);
		$this->assertSame(['Apple', 'Banana', 'Damson'], $this->names($select));
	}

	public function testWhereInWithEmptyArrayProducesInvalidSql(): void
	{
		// getWhereSql() emits "IN ()" for an empty array, which every engine rejects as a syntax error
		$this->expectException(QueryException::class);
		$this->select()->whereIn('id', [])->fetchAll();
	}

	// ────────── LIKE ──────────

	public function testWhereLike(): void
	{
		$this->assertSame(['Apple'], $this->names($this->select()->whereLike('name', 'A%')));
	}

	public function testWhereNotLike(): void
	{
		$this->assertSame(['Apple', 'Banana', 'Carrot', 'Orphan'], $this->names($this->select()->whereNotLike('name', 'D%')));
	}

	public function testOrWhereLike(): void
	{
		$select = $this->select()->where('name', 'Orphan')->orWhereLike('name', 'A%');
		$this->assertSame(['Apple', 'Orphan'], $this->names($select));
	}

	// ────────── nested grouping ──────────

	public function testNestedClosureGrouping(): void
	{
		// (category_id = 1 OR price > 5)
		$select = $this->select()->where(static fn (Where $w): Where => $w->where('category_id', 1)->orWhere('price', '>', 5));
		$this->assertSame(['Apple', 'Banana', 'Orphan'], $this->names($select));
	}

	public function testNestedWhereInstanceGrouping(): void
	{
		$group = Where::init()->where('category_id', 2)->orWhere('name', 'Orphan');
		$select = $this->select()->where($group);
		$this->assertSame(['Carrot', 'Damson', 'Orphan'], $this->names($select));
	}

	public function testNestedGroupingCombinedWithOuterCondition(): void
	{
		// price < 5 AND (category_id = 1 OR name = 'Damson')  => Apple, Banana, Damson (Orphan excluded by price)
		$select = $this->select()
			->where('price', '<', 5)
			->where(static fn (Where $w): Where => $w->where('category_id', 1)->orWhere('name', 'Damson'));
		$this->assertSame(['Apple', 'Banana', 'Damson'], $this->names($select));
	}

	// ────────── isEmpty / resetBindings ──────────

	public function testIsEmpty(): void
	{
		$where = Where::init();
		$this->assertTrue($where->isEmpty());

		$where->where('id', 1);
		$this->assertFalse($where->isEmpty());
	}

	public function testResetBindingsKeepsConditions(): void
	{
		$where = Where::init()->where('id', 1);
		$where->resetBindings();

		$this->assertFalse($where->isEmpty(), 'resetBindings() clears bindings, not the where conditions');
	}

}
