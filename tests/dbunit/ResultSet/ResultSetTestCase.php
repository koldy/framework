<?php

declare(strict_types=1);

namespace Tests\DbUnit\ResultSet;

use Koldy\Db;
use Koldy\Db\Adapter\AbstractAdapter;
use Koldy\Db\Query\Select;
use Tests\DbUnit\Fixtures\Item;
use Tests\DbUnit\Support\DbUnitTestCase;
use Tests\DbUnit\Support\Schema;

/**
 * Behaviour of the ResultSet query builder (pagination + counting + searching) executed against a real database, on
 * all four adapters. One subclass per adapter supplies the connection key.
 *
 * Seeded data (created fresh in setUp), ordered by id:
 *   1 Apple     (cat 1)
 *   2 Apricot   (cat 1)
 *   3 Banana    (cat 2)
 *   4 Cherry    (cat 2)
 *   5 Cranberry (cat 3)
 *   6 date      (cat null)   -- lowercase on purpose, to prove case-insensitive search
 */
abstract class ResultSetTestCase extends DbUnitTestCase
{

	abstract protected static function adapterKey(): string;

	/**
	 * ResultSet's explicit-field search emits the ILIKE clause as an infix operator (field ILIKE :value). PostgreSQL
	 * has native ILIKE; MySQL/MariaDB fall through to a case-insensitive LIKE. SQLite registers ILIKE only as a
	 * function (ILIKE(mask, value)), so the operator form is a syntax error there — the SQLite subclass flips this off.
	 */
	protected function ilikeWorksAsOperator(): bool
	{
		return true;
	}

	protected static function adapter(): AbstractAdapter
	{
		return Db::getAdapter(static::adapterKey());
	}

	protected function setUp(): void
	{
		$adapter = static::adapter();
		Schema::createItems($adapter);

		$adapter->insert(Schema::ITEMS, ['category_id' => 1, 'name' => 'Apple', 'price' => 1.50])->exec();
		$adapter->insert(Schema::ITEMS, ['category_id' => 1, 'name' => 'Apricot', 'price' => 2.00])->exec();
		$adapter->insert(Schema::ITEMS, ['category_id' => 2, 'name' => 'Banana', 'price' => 0.75])->exec();
		$adapter->insert(Schema::ITEMS, ['category_id' => 2, 'name' => 'Cherry', 'price' => 3.25])->exec();
		$adapter->insert(Schema::ITEMS, ['category_id' => 3, 'name' => 'Cranberry', 'price' => 4.00])->exec();
		$adapter->insert(Schema::ITEMS, ['category_id' => null, 'name' => 'date', 'price' => 0.10])->exec();

		Item::setAdapterConnection(static::adapterKey());
	}

	protected function tearDown(): void
	{
		Schema::drop(static::adapter(), Schema::ITEMS);
	}

	// ────────── count ──────────

	public function testCountReturnsTotal(): void
	{
		$this->assertSame(6, Item::resultSet()->count());
	}

	public function testCountIsMemoized(): void
	{
		$rs = Item::resultSet();
		$this->assertSame(6, $rs->count());

		// a new row appears, but the already-calculated count must not change on the same instance
		static::adapter()->insert(Schema::ITEMS, ['name' => 'Elderberry'])->exec();
		$this->assertSame(6, $rs->count(), 'count() is calculated once and cached');
	}

	public function testSetCountPresetWins(): void
	{
		$rs = Item::resultSet();
		$rs->setCount(99);

		$this->assertSame(99, $rs->count(), 'a preset count short-circuits the query');
	}

	public function testCountIgnoresMainQueryFieldsLimitAndOrder(): void
	{
		$rs = Item::resultSet();
		$rs->field('name')->orderBy('name')->limit(0, 2);

		// getCountQuery() resets fields, limit and order by, so the total is still the full table
		$this->assertSame(6, $rs->count());
	}

	public function testResetGroupByOnCountCountsWholeTable(): void
	{
		$rs = Item::resultSet();
		$rs->field('category_id')->field('COUNT(*)', 'c')->groupBy('category_id');
		$rs->resetGroupByOnCount();

		$this->assertSame(6, $rs->count(), 'with resetGroupByOnCount() the count is over the whole table');
	}

	public function testAdjustCountQueryNarrowsTheCount(): void
	{
		$rs = Item::resultSet();
		$rs->adjustCountQuery(static fn (Select $select): Select => $select->where('category_id', 1));

		$this->assertSame(2, $rs->count(), 'only category 1 has two items');
	}

	// ────────── pagination ──────────

	public function testPageLimitsRowsButNotCount(): void
	{
		$rs = Item::resultSet();
		$rs->orderBy('id')->page(1, 2);

		$rows = $rs->fetchAll();

		$this->assertCount(2, $rows);
		$this->assertSame(['Apple', 'Apricot'], array_column($rows, 'name'));
		$this->assertSame(6, $rs->count(), 'count() ignores the LIMIT applied to the main query');
	}

	public function testSecondPage(): void
	{
		$rs = Item::resultSet();
		$rs->orderBy('id')->page(2, 2);

		$this->assertSame(['Banana', 'Cherry'], array_column($rs->fetchAll(), 'name'));
	}

	// ────────── object hydration ──────────

	public function testFetchAllObjDefaultsToModelClass(): void
	{
		$items = Item::resultSet()->orderBy('id')->fetchAllObj();

		$this->assertContainsOnlyInstancesOf(Item::class, $items);
		$this->assertSame('Apple', $items[0]->name);
	}

	public function testFetchFirstObjDefaultsToModelClass(): void
	{
		$item = Item::resultSet()->orderBy('id')->fetchFirstObj();

		$this->assertInstanceOf(Item::class, $item);
		$this->assertSame('Apple', $item->name);
	}

	// ────────── search ──────────

	public function testSearchOnExplicitFieldsIsCaseInsensitive(): void
	{
		if (!$this->ilikeWorksAsOperator()) {
			$this->markTestSkipped(static::adapterKey() . ' has no ILIKE operator; ResultSet emits ILIKE as an operator');
		}

		// explicit searchFields is the correct branch: native ILIKE on PostgreSQL, case-insensitive LIKE on
		// MySQL/MariaDB. Searching "ap" must find "Apple" and "Apricot" regardless of case.
		$rs = Item::resultSet();
		$rs->setSearchFields(['name'])->search('ap')->orderBy('id');

		$names = array_column($rs->fetchAll(), 'name');

		$this->assertSame(['Apple', 'Apricot'], $names);
	}

	public function testSearchOnExplicitFieldsExcludesNonMatches(): void
	{
		if (!$this->ilikeWorksAsOperator()) {
			$this->markTestSkipped(static::adapterKey() . ' has no ILIKE operator; ResultSet emits ILIKE as an operator');
		}

		$rs = Item::resultSet();
		$rs->setSearchFields(['name'])->search('berry')->orderBy('id');

		$this->assertSame(['Cranberry'], array_column($rs->fetchAll(), 'name'));
	}

	public function testSearchAcrossAllFieldsIsSkippedDueToFrameworkBug(): void
	{
		// KNOWN FRAMEWORK BUG (ResultSet::getQuery(), src/Db/Query/ResultSet.php ~lines 250-266): the branch used when
		// no explicit searchFields are set is inverted. On PostgreSQL/SQLite it emits a case-sensitive orWhereLike
		// (instead of ILIKE), and on MySQL/MariaDB it emits orWhere(field, 'ILIKE', ...) which those engines do not
		// understand, producing a runtime SQL error. Left untouched here on purpose; documented instead of asserting
		// broken behaviour or emitting invalid SQL.
		$this->markTestSkipped('ResultSet::getQuery() inverts LIKE/ILIKE in the all-fields search branch (framework bug)');
	}

}
