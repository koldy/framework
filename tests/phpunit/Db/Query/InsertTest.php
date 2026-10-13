<?php

declare(strict_types=1);

namespace Tests\PhpUnit\Db\Query;

use Koldy\Application;
use Koldy\Db;
use Koldy\Db\Adapter\MariaDB;
use Koldy\Db\Adapter\MySQL;
use Koldy\Db\Adapter\PostgreSQL;
use Koldy\Db\Query\Bindings;
use Koldy\Db\Query\Insert;
use Koldy\Db\Query\Select;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Tests for the Insert query builder (src/Db/Query/Insert.php), focused on SQL generation and the
 * INSERT ... RETURNING clause.
 *
 * Adapters are declared in config but never connected: PostgreSQL, MySQL and MariaDB decide
 * supportsReturning() without touching PDO, so no database server is required here. Real-server
 * behaviour lives in the dbunit suite.
 */
class InsertTest extends TestCase
{

	private static bool $appInitialized = false;

	public static function setUpBeforeClass(): void
	{
		if (!self::$appInitialized) {
			$_SERVER['SCRIPT_FILENAME'] = __FILE__;

			// other test classes in the same process may have registered a 'database' config already
			Application::removeConfig('database');

			$connection = [
				'host' => '127.0.0.1',
				'username' => 'never',
				'password' => 'connected',
				'database' => 'nowhere',
				'persistent' => false,
			];

			Application::useConfig([
				'site_url' => 'http://localhost',
				'env' => Application::TEST,
				'key' => 'InsertTestKey12345678901',
				'timezone' => 'UTC',
				'paths' => [
					'application' => __DIR__ . '/',
					'storage' => __DIR__ . '/',
				],
				'configs' => [
					'database' => [
						'pg' => ['type' => 'postgres'] + $connection,
						'my' => ['type' => 'mysql'] + $connection,
						'maria' => ['type' => 'mariadb'] + $connection,
						// real in-memory databases, each adapter holds its own, for tests that execute
						'lite_a' => ['type' => 'sqlite', 'path' => ':memory:'],
						'lite_b' => ['type' => 'sqlite', 'path' => ':memory:'],
					],
				],
			]);

			self::$appInitialized = true;
		}
	}

	public static function tearDownAfterClass(): void
	{
		Db::removeAdapters();
		Application::removeConfig('database');
	}

	protected function setUp(): void
	{
		// bind names carry a process-global counter; reset so placeholder names are predictable per test
		(new ReflectionProperty(Bindings::class, 'index'))->setValue(null, 0);
	}

	// ────────── adapter capability ──────────

	public function testPostgreSqlSupportsReturning(): void
	{
		$adapter = Db::getAdapter('pg');

		$this->assertInstanceOf(PostgreSQL::class, $adapter);
		$this->assertTrue($adapter->supportsReturning());
	}

	public function testMySqlDoesNotSupportReturning(): void
	{
		$adapter = Db::getAdapter('my');

		$this->assertInstanceOf(MySQL::class, $adapter);
		$this->assertNotInstanceOf(MariaDB::class, $adapter);
		$this->assertFalse($adapter->supportsReturning());
	}

	public function testMariaDbTypeResolvesToMariaDbAdapterWhichSupportsReturning(): void
	{
		$adapter = Db::getAdapter('maria');

		$this->assertInstanceOf(MariaDB::class, $adapter);
		$this->assertInstanceOf(MySQL::class, $adapter, 'MariaDB adapter must remain a MySQL adapter for instanceof checks');
		$this->assertTrue($adapter->supportsReturning());
	}

	// ────────── SQL generation ──────────

	public function testInsertWithoutReturningIsUnchanged(): void
	{
		$insert = new Insert('users', ['name' => 'John'], 'pg');
		$sql = $insert->getSQL();

		$this->assertMatchesRegularExpression('/^INSERT INTO users \(name\)\nVALUES\n\t\(:i0\d+\)$/', $sql);
		$this->assertStringNotContainsString('RETURNING', $sql);
	}

	public function testReturningWithoutArgumentsRequestsAllColumns(): void
	{
		$insert = (new Insert('users', ['name' => 'John'], 'pg'))->returning();

		$this->assertStringEndsWith(")\nRETURNING *", $insert->getSQL());
	}

	public function testReturningWithColumns(): void
	{
		$insert = (new Insert('users', ['name' => 'John'], 'pg'))->returning('id', 'created_at');

		$this->assertStringEndsWith(")\nRETURNING id, created_at", $insert->getSQL());
	}

	public function testReturningAcceptsExpressions(): void
	{
		$insert = (new Insert('users', ['name' => 'John'], 'pg'))->returning('id', Db::expr('created_at AS at'));

		$this->assertStringEndsWith(")\nRETURNING id, created_at AS at", $insert->getSQL());
	}

	public function testReturningIsSilentlyDroppedOnMySql(): void
	{
		$insert = (new Insert('users', ['name' => 'John'], 'my'))->returning();
		$sql = $insert->getSQL();

		$this->assertStringNotContainsString('RETURNING', $sql);
		$this->assertMatchesRegularExpression('/^INSERT INTO users \(name\)\nVALUES\n\t\(:i0\d+\)$/', $sql);
	}

	public function testReturningIsKeptOnMariaDb(): void
	{
		$insert = (new Insert('users', ['name' => 'John'], 'maria'))->returning('id');

		$this->assertStringEndsWith(")\nRETURNING id", $insert->getSQL());
	}

	public function testRetargetingAdapterReEvaluatesReturningSupport(): void
	{
		$insert = (new Insert('users', ['name' => 'John'], 'my'))->returning();
		$this->assertStringNotContainsString('RETURNING', $insert->getSQL());

		$insert->setAdapter('pg');
		$this->assertStringEndsWith("\nRETURNING *", $insert->getSQL());
	}

	public function testMultiRowInsertCarriesReturningOnce(): void
	{
		$insert = (new Insert('users', null, 'pg'))
			->addRows([
				['name' => 'Alice'],
				['name' => 'Bob'],
			])
			->returning('id');

		$sql = $insert->getSQL();

		$this->assertSame(1, substr_count($sql, 'RETURNING'));
		$this->assertMatchesRegularExpression('/VALUES\n\t\(:i0\d+\),\n\t\(:i1\d+\)\nRETURNING id$/', $sql);
	}

	public function testInsertSelectCarriesReturning(): void
	{
		$select = (new Select('archived_users'))->field('name');
		$insert = (new Insert('users', null, 'pg'))
			->field('name')
			->selectFrom($select)
			->returning('id');

		$sql = $insert->getSQL();

		$this->assertStringStartsWith("INSERT INTO users (name)\n(\n\t", $sql);
		$this->assertStringEndsWith("\n)\nRETURNING id", $sql);
	}

	// ────────── returned rows before execution ──────────

	public function testReturnedRowsAreEmptyBeforeExec(): void
	{
		$insert = (new Insert('users', ['name' => 'John'], 'pg'))->returning();

		$this->assertSame([], $insert->getReturnedRows());
		$this->assertNull($insert->getReturnedRow());
	}

	public function testCloneClearsReturnedRows(): void
	{
		$insert = (new Insert('users', ['name' => 'John'], 'pg'))->returning();

		// simulate rows fetched by a previous exec() without needing a database
		(new ReflectionProperty(Insert::class, 'returnedRows'))->setValue($insert, [['id' => 1]]);
		$this->assertSame(['id' => 1], $insert->getReturnedRow());

		$copy = clone $insert;

		$this->assertSame([], $copy->getReturnedRows());
		$this->assertNull($copy->getReturnedRow());
		$this->assertStringEndsWith("\nRETURNING *", $copy->getSQL(), 'clone keeps the RETURNING clause, only drops fetched rows');
	}

	// ────────── exec() with an explicit adapter ──────────

	/**
	 * (Re)create an empty "notes" table on every given connection
	 */
	private static function createNotesTable(string ...$connections): void
	{
		foreach ($connections as $connection) {
			$adapter = Db::getAdapter($connection);
			$adapter->query('DROP TABLE IF EXISTS notes')->exec();
			$adapter->query('CREATE TABLE notes (id INTEGER PRIMARY KEY, body TEXT NOT NULL)')->exec();
		}
	}

	private static function countNotes(string $connection): int
	{
		return count(Db::getAdapter($connection)->query('SELECT id FROM notes')->fetchAll());
	}

	public function testExecWithAdapterTargetsThatAdapterForOneCallOnly(): void
	{
		self::createNotesTable('lite_a', 'lite_b');
		$insert = new Insert('notes', ['body' => 'hello'], 'lite_a');

		$insert->exec('lite_b');
		$insert->exec();

		// same as every other query builder: the argument is a one-off, the Insert keeps its own adapter
		$this->assertSame('lite_a', $insert->getAdapterConnection());
		$this->assertSame(1, self::countNotes('lite_a'));
		$this->assertSame(1, self::countNotes('lite_b'));
	}

	public function testExecWithAdapterDecidesReturningForThatAdapterOnly(): void
	{
		self::createNotesTable('lite_b');
		$insert = (new Insert('notes', ['body' => 'hello'], 'my'))->returning('body');

		$insert->exec('lite_b');

		// RETURNING was emitted because lite_b (SQLite) supports it, although the Insert's own adapter (MySQL) doesn't
		$this->assertSame([['body' => 'hello']], $insert->getReturnedRows());
		$this->assertSame('my', $insert->getAdapterConnection());
		$this->assertStringNotContainsString('RETURNING', $insert->getSQL(), 'back on MySQL, which has no RETURNING');
	}

}
