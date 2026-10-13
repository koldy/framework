<?php

declare(strict_types=1);

namespace Tests\DbUnit\Model;

use Koldy\Db;
use Koldy\Db\Adapter\AbstractAdapter;
use Koldy\Db\Adapter\Exception as AdapterException;
use Koldy\Db\Exception\NotFoundException;
use Koldy\Db\Query\Insert;
use Koldy\Db\Query\ResultSet;
use Koldy\Db\Query\Select;
use Koldy\Db\Where;
use Tests\DbUnit\Fixtures\Item;
use Tests\DbUnit\Fixtures\ItemReturning;
use Tests\DbUnit\Support\DbUnitTestCase;
use Tests\DbUnit\Support\Schema;

/**
 * Behaviour of the ActiveRecord Model (single primary key) executed against a real database, on all four adapters,
 * using the Item fixture over Schema::ITEMS. Item keeps the Model default ($returning = false), so these tests run
 * the lastInsertId() path; ItemReturning opts in to RETURNING. One subclass per adapter supplies the connection key
 * and whether the adapter emits RETURNING (which decides what an opted-in create() holds; MySQL falls back to
 * lastInsertId).
 */
abstract class ModelTestCase extends DbUnitTestCase
{

	abstract protected static function adapterKey(): string;

	/**
	 * Is this adapter expected to send RETURNING (so create()/save() hold the full stored row)?
	 */
	abstract protected function expectsReturning(): bool;

	protected static function adapter(): AbstractAdapter
	{
		return Db::getAdapter(static::adapterKey());
	}

	protected function setUp(): void
	{
		Schema::createItems(static::adapter());
		Item::setAdapterConnection(static::adapterKey());
	}

	protected function tearDown(): void
	{
		Schema::drop(static::adapter(), Schema::ITEMS);
	}

	/**
	 * Row with keys sorted so it can be compared regardless of column order
	 */
	private static function sorted(array $row): array
	{
		ksort($row);
		return $row;
	}

	// ────────── create ──────────

	public function testCreateReturnsModelHoldingStoredRow(): void
	{
		$item = ItemReturning::create(['name' => 'Alice']);

		$this->assertNotNull($item->id);
		$this->assertSame('Alice', $item->name);

		$fetched = ItemReturning::fetchOne($item->id);
		$this->assertNotNull($fetched);

		if ($this->expectsReturning()) {
			$this->assertArrayHasKey('created_at', $item->getData());
			$this->assertArrayHasKey('active', $item->getData());
			$this->assertNotNull($item->created_at, 'RETURNING must bring back DB defaults');
			$this->assertSame(self::sorted($fetched->getData()), self::sorted($item->getData()));
		} else {
			$this->assertSame(['id', 'name'], array_keys(self::sorted($item->getData())));
			$this->assertEquals($fetched->id, $item->id);
		}
	}

	public function testCreateWithExplicitPrimaryKeyKeepsIt(): void
	{
		$item = Item::create(['id' => 4242, 'name' => 'Fixed']);

		$this->assertEquals(4242, $item->id);
		$this->assertNotNull(Item::fetchOne(4242));
	}

	// ────────── save ──────────

	public function testSaveInsertsThenNoopsWhenUnchanged(): void
	{
		$item = new Item(['name' => 'Dave']);

		$this->assertSame(0, $item->save(), 'save() returns 0 for an insert');
		$this->assertNotNull($item->id);
		$this->assertSame(-1, $item->save(), 'nothing changed, so save() must not touch the database');
	}

	public function testSaveUpdatesReturningAffectedRows(): void
	{
		$item = Item::create(['name' => 'Eve']);
		$before = $item->getData();

		$item->name = 'Eve Updated';
		$this->assertSame(1, $item->save());

		$expected = $before;
		$expected['name'] = 'Eve Updated';
		$this->assertSame($expected, $item->getData(), 'an UPDATE never re-reads the row');
		$this->assertSame('Eve Updated', Item::fetchOne($item->id)?->name);
	}

	public function testSaveTreatsStringEqualScalarAsUnchanged(): void
	{
		$item = Item::create(['name' => 'Frank']);

		// same value, different scalar type: (string)$id === (string)$id so save() must detect no change
		$item->id = (string)$item->id;
		$this->assertSame(-1, $item->save());
	}

	// ────────── update (static) ──────────

	public function testUpdateByPrimaryKey(): void
	{
		$item = Item::create(['name' => 'Grace']);

		$affected = Item::update(['name' => 'Grace Hopper'], $item->id);

		$this->assertSame(1, $affected);
		$this->assertSame('Grace Hopper', Item::fetchOne($item->id)?->name);
	}

	public function testUpdateByAssocWhere(): void
	{
		Item::create(['name' => 'A', 'category_id' => 7]);
		Item::create(['name' => 'B', 'category_id' => 7]);
		Item::create(['name' => 'C', 'category_id' => 8]);

		$affected = Item::update(['category_id' => 9], ['category_id' => 7]);

		$this->assertSame(2, $affected);
	}

	public function testUpdateByWhereInstance(): void
	{
		Item::create(['name' => 'Cheap', 'price' => 1]);
		Item::create(['name' => 'Pricey', 'price' => 100]);

		$affected = Item::update(['name' => 'Expensive'], Where::init()->where('price', '>', 50));

		$this->assertSame(1, $affected);
		$this->assertSame('Expensive', Item::fetchOne('price', 100)?->name);
	}

	public function testUpdateWithoutWhereTouchesEveryRow(): void
	{
		Item::create(['name' => 'One']);
		Item::create(['name' => 'Two']);
		Item::create(['name' => 'Three']);

		$affected = Item::update(['name' => 'Same']);

		$this->assertSame(3, $affected);
		$this->assertSame(3, Item::count(['name' => 'Same']));
	}

	// ────────── increment ──────────

	public function testIncrement(): void
	{
		$item = Item::create(['name' => 'Counter', 'price' => 10]);

		$affected = Item::increment('price', $item->id, 5);

		$this->assertSame(1, $affected);
		$this->assertEquals(15.0, (float)Item::fetchOne($item->id)?->price);
	}

	// ────────── delete / destroy ──────────

	public function testDeleteByPrimaryKey(): void
	{
		$item = Item::create(['name' => 'Doomed']);

		$this->assertSame(1, Item::delete($item->id));
		$this->assertNull(Item::fetchOne($item->id));
	}

	public function testDeleteByAssoc(): void
	{
		Item::create(['name' => 'X', 'category_id' => 3]);
		Item::create(['name' => 'Y', 'category_id' => 3]);

		$this->assertSame(2, Item::delete(['category_id' => 3]));
	}

	public function testDestroyInstance(): void
	{
		$item = Item::create(['name' => 'Bye']);

		$this->assertSame(1, $item->destroy());
		$this->assertSame(0, Item::count());
	}

	// ────────── fetchOne ──────────

	public function testFetchOneByPrimaryKey(): void
	{
		$item = Item::create(['name' => 'Hank']);

		$this->assertSame('Hank', Item::fetchOne($item->id)?->name);
	}

	public function testFetchOneByFieldValue(): void
	{
		Item::create(['name' => 'Ivy']);

		$this->assertNotNull(Item::fetchOne('name', 'Ivy'));
		$this->assertNull(Item::fetchOne('name', 'Nope'));
	}

	public function testFetchOneByWhereAndAssoc(): void
	{
		Item::create(['name' => 'Jane', 'category_id' => 5]);

		$this->assertSame('Jane', Item::fetchOne(Where::init()->where('name', 'Jane'))?->name);
		$this->assertSame('Jane', Item::fetchOne(['name' => 'Jane', 'category_id' => 5])?->name);
	}

	public function testFetchOneOrFailThrowsWhenMissing(): void
	{
		$this->expectException(NotFoundException::class);
		Item::fetchOneOrFail(999999);
	}

	// ────────── fetch / fetchWithKey / all ──────────

	public function testFetchWithWhereIsCloned(): void
	{
		Item::create(['name' => 'K', 'category_id' => 1]);
		Item::create(['name' => 'L', 'category_id' => 1]);

		$where = Where::init()->where('category_id', 1);

		// reuse the same Where twice; Model::fetch() clones it, so bindings must not be corrupted
		$first = Item::fetch($where, null, 'id', 'ASC');
		$second = Item::fetch($where, null, 'id', 'ASC');

		$this->assertCount(2, $first);
		$this->assertCount(2, $second);
		$this->assertContainsOnlyInstancesOf(Item::class, $first);
	}

	public function testFetchWithLimitAndOrder(): void
	{
		foreach (['a', 'b', 'c', 'd'] as $n) {
			Item::create(['name' => $n]);
		}

		$page = Item::fetch(Where::init()->where('id', '>', 0), null, 'name', 'ASC', 2, 1);

		$this->assertSame(['b', 'c'], array_map(static fn (Item $i): string => $i->name, $page));
	}

	public function testFetchWithKeyIsKeyedByColumn(): void
	{
		$a = Item::create(['name' => 'Alpha']);
		$b = Item::create(['name' => 'Beta']);

		$byName = Item::fetchWithKey('name', Where::init()->where('id', '>', 0));

		$this->assertArrayHasKey('Alpha', $byName);
		$this->assertArrayHasKey('Beta', $byName);
		$this->assertEquals($a->id, $byName['Alpha']->id);
		$this->assertEquals($b->id, $byName['Beta']->id);
	}

	public function testAll(): void
	{
		Item::create(['name' => 'z']);
		Item::create(['name' => 'y']);

		$all = Item::all('name', 'ASC');

		$this->assertSame(['y', 'z'], array_map(static fn (Item $i): string => $i->name, $all));
	}

	// ────────── scalar fetchers ──────────

	public function testFetchKeyValue(): void
	{
		$a = Item::create(['name' => 'One']);
		$b = Item::create(['name' => 'Two']);

		$map = Item::fetchKeyValue('id', 'name');

		$this->assertSame('One', $map[$a->id] ?? $map[(string)$a->id]);
		$this->assertSame('Two', $map[$b->id] ?? $map[(string)$b->id]);
	}

	public function testFetchArrayOf(): void
	{
		Item::create(['name' => 'p']);
		Item::create(['name' => 'q']);

		$names = Item::fetchArrayOf('name', null, 'name', 'ASC');

		$this->assertSame(['p', 'q'], $names);
	}

	public function testFetchOneValue(): void
	{
		$item = Item::create(['name' => 'Solo']);

		$this->assertSame('Solo', Item::fetchOneValue('name', ['id' => $item->id]));
		$this->assertNull(Item::fetchOneValue('name', ['id' => 999999]));
	}

	public function testFetchOneValueOrFailThrows(): void
	{
		$this->expectException(NotFoundException::class);
		Item::fetchOneValueOrFail('name', ['id' => 999999]);
	}

	// ────────── isUnique / count ──────────

	public function testIsUnique(): void
	{
		$item = Item::create(['name' => 'Unique']);

		$this->assertTrue(Item::isUnique('name', 'Other'));
		$this->assertFalse(Item::isUnique('name', 'Unique'));
		$this->assertTrue(Item::isUnique('name', 'Unique', $item->id, 'id'), 'excluding the owning row makes it unique again');
	}

	public function testCount(): void
	{
		$this->assertSame(0, Item::count());

		Item::create(['name' => 'a', 'category_id' => 1]);
		Item::create(['name' => 'b', 'category_id' => 1]);
		Item::create(['name' => 'c', 'category_id' => 2]);

		$this->assertSame(3, Item::count());
		$this->assertSame(2, Item::count(['category_id' => 1]));
		$this->assertSame(1, Item::count(Where::init()->where('category_id', 2)));
	}

	// ────────── builder getters ──────────

	public function testBuilderGetters(): void
	{
		$this->assertInstanceOf(ResultSet::class, Item::resultSet());
		$this->assertInstanceOf(Insert::class, Item::insert());
		$this->assertInstanceOf(Select::class, Item::select());
		$this->assertSame('koldy_dbunit_items', Item::getTableName());
	}

	// ────────── reload ──────────

	public function testReloadRestoresDatabaseValues(): void
	{
		$item = Item::create(['name' => 'Original']);
		$item->name = 'Dirty In Memory';

		$item->reload();

		$this->assertSame('Original', $item->name);
	}

	public function testReloadWithoutPrimaryKeyThrows(): void
	{
		$this->expectException(AdapterException::class);
		(new Item(['name' => 'No PK']))->reload();
	}

	public function testReloadWhenRowMissingThrows(): void
	{
		$item = Item::create(['name' => 'Gone']);
		Item::delete($item->id);

		$this->expectException(AdapterException::class);
		$item->reload();
	}

	// ────────── data access ──────────

	public function testHasAndSetData(): void
	{
		$item = new Item(['name' => 'H']);

		$this->assertTrue($item->has('name'));
		$this->assertFalse($item->has('price'));

		$item->setData(['price' => 5]);
		$this->assertTrue($item->has('price'));
	}

	public function testGetOnUnsetDataThrows(): void
	{
		$this->expectException(AdapterException::class);
		// @phpstan-ignore-next-line accessing a property on a model with no data
		(new Item())->name;
	}

	public function testToStringIsClassAndJson(): void
	{
		$item = Item::create(['name' => 'Str']);

		$this->assertStringStartsWith(Item::class . '={', (string)$item);
		$this->assertStringContainsString('"name":"Str"', (string)$item);
	}

	public function testSerializeRoundTripPreservesDirtyBaseline(): void
	{
		$item = Item::create(['name' => 'Ser']);

		$restored = unserialize(serialize($item));

		$this->assertEquals($item->getData(), $restored->getData());
		$this->assertSame(-1, $restored->save(), 'the original-data baseline survives serialization, so an unchanged save() is a no-op');
	}

}
