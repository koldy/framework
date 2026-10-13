<?php

declare(strict_types=1);

namespace Tests\DbUnit\Adapter;

use Koldy\Config\Exception as ConfigException;
use Koldy\Db;
use Koldy\Db\Adapter\AbstractAdapter;
use Koldy\Db\Adapter\Exception as AdapterException;
use Koldy\Db\Expr;
use Koldy\Db\Query\Exception as QueryException;
use Tests\DbUnit\Support\DbUnitTestCase;
use Tests\DbUnit\Support\Schema;

/**
 * Adapter-level behaviour executed against a real database, on all four adapters: transaction nesting, reconnect, raw
 * queries and bindings, error wrapping, lastInsertId and the Db facade. One subclass per adapter supplies the
 * connection key and, through small hooks, the engine-specific expectations (RETURNING support, the lastInsertId
 * sequence name, whether in-memory data survives a reconnect).
 */
abstract class AdapterTestCase extends DbUnitTestCase
{

	abstract protected static function adapterKey(): string;

	/**
	 * Fixed expectation for supportsReturning(), or null when it is version-gated (SQLite) and only the type matters
	 */
	protected function expectsSupportsReturning(): ?bool
	{
		return null;
	}

	/**
	 * The sequence/key name getLastInsertId() needs; PostgreSQL needs the SERIAL sequence, others take null
	 */
	protected function lastInsertIdKeyName(): ?string
	{
		return null;
	}

	/**
	 * Does inserted data survive a reconnect? False for SQLite :memory:, whose database is dropped when the connection
	 * closes.
	 */
	protected function dataSurvivesReconnect(): bool
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
		Schema::drop($adapter, Schema::ITEMS);
		Schema::createItems($adapter);
	}

	protected function tearDown(): void
	{
		// MySQL/MariaDB run unbuffered queries; make sure no result set is left pending on the shared adapter before
		// the next test issues a query (a stray unfetched SELECT would otherwise poison the connection)
		try {
			static::adapter()->getStatement()->closeCursor();
		} catch (\Throwable) {
			// no statement prepared (e.g. after close()), nothing to release
		}

		Schema::drop(static::adapter(), Schema::ITEMS);
	}

	private function countItems(): int
	{
		$rows = static::adapter()->query('SELECT COUNT(*) AS cnt FROM ' . Schema::ITEMS)->fetchAll();
		return (int)$rows[0]['cnt'];
	}

	// ────────── transactions ──────────

	public function testCommitPersistsRow(): void
	{
		$adapter = static::adapter();
		$adapter->beginTransaction();
		$adapter->insert(Schema::ITEMS, ['name' => 'Committed'])->exec();
		$adapter->commit();

		$this->assertSame(1, $this->countItems());
	}

	public function testRollBackDiscardsRow(): void
	{
		$adapter = static::adapter();
		$adapter->beginTransaction();
		$adapter->insert(Schema::ITEMS, ['name' => 'Rolled back'])->exec();
		$adapter->rollBack();

		$this->assertSame(0, $this->countItems());
	}

	public function testNestedCommitPersists(): void
	{
		$adapter = static::adapter();
		$adapter->beginTransaction();
		$adapter->beginTransaction();
		$adapter->insert(Schema::ITEMS, ['name' => 'Nested'])->exec();
		$adapter->commit();
		$adapter->commit();

		$this->assertSame(1, $this->countItems(), 'the real commit happens only when the outermost transaction commits');
	}

	public function testNestedRollBackDiscards(): void
	{
		$adapter = static::adapter();
		$adapter->beginTransaction();
		$adapter->beginTransaction();
		$adapter->insert(Schema::ITEMS, ['name' => 'Nested'])->exec();
		$adapter->rollBack(); // inner: no-op at depth 2
		$adapter->rollBack(); // outer: real rollback at depth 1

		$this->assertSame(0, $this->countItems(), 'the inner rollBack is a no-op; the outer one discards everything');
	}

	// ────────── reconnect ──────────

	public function testReconnectKeepsAdapterUsable(): void
	{
		$adapter = static::adapter();
		$adapter->insert(Schema::ITEMS, ['name' => 'BeforeReconnect'])->exec();

		$adapter->reconnect();

		// reconnect() runs SELECT 1 internally; on unbuffered MySQL that result set must be released before the next
		// query can run on the same connection
		$adapter->getStatement()->closeCursor();

		if ($this->dataSurvivesReconnect()) {
			$this->assertSame(1, $this->countItems(), 'data written before reconnect is still there');
		} else {
			// SQLite :memory: is wiped on close/reconnect; just prove the fresh connection works
			$rows = $adapter->query('SELECT 1 AS one')->fetchAll();
			$this->assertSame(1, (int)$rows[0]['one']);
		}
	}

	// ────────── raw queries ──────────

	public function testRawQueryCount(): void
	{
		$adapter = static::adapter();
		$adapter->insert(Schema::ITEMS, ['name' => 'A'])->exec();
		$adapter->insert(Schema::ITEMS, ['name' => 'B'])->exec();

		$rows = $adapter->query('SELECT COUNT(*) AS cnt FROM ' . Schema::ITEMS)->fetchAll();
		$this->assertSame(2, (int)$rows[0]['cnt']);
	}

	public function testRawQueryWithBindings(): void
	{
		$adapter = static::adapter();
		$adapter->insert(Schema::ITEMS, ['name' => 'Bound'])->exec();
		$adapter->insert(Schema::ITEMS, ['name' => 'Other'])->exec();

		$rows = $adapter->query('SELECT name FROM ' . Schema::ITEMS . ' WHERE name = :n', ['n' => 'Bound'])->fetchAll();

		$this->assertCount(1, $rows);
		$this->assertSame('Bound', $rows[0]['name']);
	}

	// ────────── error handling ──────────

	public function testBadQueryThrowsQueryExceptionCarryingSql(): void
	{
		try {
			static::adapter()->query('SELECT * FROM koldy_dbunit_missing_table_xyz')->exec();
			$this->fail('Expected a QueryException for a missing table');
		} catch (QueryException $e) {
			$this->assertNotNull($e->getSql(), 'the failing SQL is attached to the exception');
		}
	}

	public function testGetStatementBeforePrepareThrows(): void
	{
		static::adapter()->close();

		$this->expectException(AdapterException::class);
		static::adapter()->getStatement();
	}

	// ────────── lastInsertId ──────────

	public function testGetLastInsertIdReturnsGeneratedId(): void
	{
		$adapter = static::adapter();
		$adapter->insert(Schema::ITEMS, ['name' => 'LastId'])->exec();

		$id = $adapter->getLastInsertId($this->lastInsertIdKeyName());
		$this->assertTrue(is_numeric($id), 'lastInsertId returns a numeric string');

		$rows = $adapter->query('SELECT name FROM ' . Schema::ITEMS . ' WHERE id = :id', ['id' => (int)$id])->fetchAll();
		$this->assertSame('LastId', $rows[0]['name']);
	}

	// ────────── supportsReturning ──────────

	public function testSupportsReturningMatrix(): void
	{
		$actual = static::adapter()->supportsReturning();
		$expected = $this->expectsSupportsReturning();

		if ($expected === null) {
			$this->assertIsBool($actual, 'SQLite support is version-gated; it must at least return a bool');
		} else {
			$this->assertSame($expected, $actual);
		}
	}

	// ────────── Db facade ──────────

	public function testDbHasAdapterAfterUse(): void
	{
		static::adapter();
		$this->assertTrue(Db::hasAdapter(static::adapterKey()));
	}

	public function testDbGetAdapterReturnsSameInstance(): void
	{
		$this->assertSame(Db::getAdapter(static::adapterKey()), Db::getAdapter(static::adapterKey()));
	}

	public function testDbDefaultAdapterKeyIsFirstConfigKey(): void
	{
		$this->assertSame('postgres', Db::getDefaultAdapterKey());
	}

	public function testDbExprStringifiesVerbatim(): void
	{
		$expr = Db::expr('NOW()');

		$this->assertInstanceOf(Expr::class, $expr);
		$this->assertSame('NOW()', (string)$expr);
	}

	public function testDbGetUnknownAdapterThrows(): void
	{
		$this->expectException(ConfigException::class);
		Db::getAdapter('this_key_does_not_exist');
	}

	public function testDbRegisterDuplicateTypeThrows(): void
	{
		$this->expectException(ConfigException::class);
		Db::registerType('mysql', 'Whatever');
	}

	public function testDbAddDuplicateAdapterThrows(): void
	{
		$this->expectException(ConfigException::class);
		Db::addAdapter(static::adapterKey(), static::adapter());
	}

}
