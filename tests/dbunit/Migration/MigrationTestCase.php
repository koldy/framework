<?php

declare(strict_types=1);

namespace Tests\DbUnit\Migration;

use Koldy\Application;
use Koldy\Db;
use Koldy\Db\Adapter\AbstractAdapter;
use Koldy\Db\Migration\Exception as MigrationException;
use Koldy\Db\Migration\Manager;
use PHPUnit\Framework\TestCase;

/**
 * Behaviour of the database migration Manager executed against a real database, on all four adapters.
 *
 * The Manager is coupled to the KoldyMigration model, which stores its tracking table on the "koldy_migration"
 * connection when one is configured. Each subclass therefore points "koldy_migration" at its target engine, and the
 * generated migration scripts run their DDL through that same connection (Db::getAdapter('koldy_migration')), so
 * tracking and schema changes always land in one consistent database — including SQLite :memory:, which stays a single
 * shared connection because Db caches one adapter instance per key.
 *
 * One subclass per adapter supplies the connection key; everything else is shared here.
 */
abstract class MigrationTestCase extends TestCase
{

	protected const string MIGRATION_CONNECTION = 'koldy_migration';

	/**
	 * Global counter so every generated migration class/file/table name is unique across the whole test process
	 * (all four subclasses run in one PHP process, so class names must never collide).
	 */
	private static int $counter = 0;

	private static string $appDir = '';

	abstract protected static function adapterKey(): string;

	public static function setUpBeforeClass(): void
	{
		$_SERVER['SCRIPT_FILENAME'] = __FILE__;

		Db::removeAdapters();
		Application::removeConfig('database');

		self::$appDir = sys_get_temp_dir() . '/koldy_dbunit_migration_' . static::adapterKey() . '/';
		if (!is_dir(self::$appDir)) {
			mkdir(self::$appDir, 0755, true);
		}

		$database = require __DIR__ . '/../config.php';
		// route the migration tracking table (and the DDL our scripts run) to the target engine through one connection
		$database[self::MIGRATION_CONNECTION] = $database[static::adapterKey()];

		Application::useConfig([
			'site_url' => 'http://localhost',
			'env' => Application::TEST,
			'key' => 'DbUnitMigrationKey123456',
			'timezone' => 'UTC',
			'paths' => [
				'application' => self::$appDir,
				'storage' => self::$appDir,
			],
			'configs' => [
				'database' => $database,
			],
		]);
	}

	public static function tearDownAfterClass(): void
	{
		Db::removeAdapters();
		Application::removeConfig('database');

		self::rrmdir(self::$appDir);
	}

	protected function setUp(): void
	{
		$this->resetMigrationsDir();

		// always start from a clean tracking table
		Db::getAdapter(self::MIGRATION_CONNECTION)->query('DROP TABLE IF EXISTS koldy_migration')->exec();
	}

	protected function tearDown(): void
	{
		$this->resetMigrationsDir();
		Db::getAdapter(self::MIGRATION_CONNECTION)->query('DROP TABLE IF EXISTS koldy_migration')->exec();
	}

	protected static function adapter(): AbstractAdapter
	{
		return Db::getAdapter(self::MIGRATION_CONNECTION);
	}

	private function migrationsDir(): string
	{
		return self::$appDir . 'migrations';
	}

	private function resetMigrationsDir(): void
	{
		$dir = $this->migrationsDir();
		if (is_dir($dir)) {
			foreach (glob($dir . '/*.php') ?: [] as $file) {
				unlink($file);
			}
		} else {
			mkdir($dir, 0755, true);
		}
	}

	/**
	 * Write a migration file whose up() creates $table and down() drops it. Returns [timestamp, table].
	 *
	 * @return array{0: int, 1: string}
	 */
	private function writeTableMigration(): array
	{
		self::$counter++;
		$timestamp = 1700000000 + self::$counter;
		// the file name must contain exactly one underscore (Manager splits on it), so the name part has none
		$name = 'Create' . ucfirst(static::adapterKey()) . 'Table' . self::$counter;
		$table = 'koldy_dbunit_mig' . self::$counter;

		$class = "Migration_{$timestamp}_{$name}";
		$php = "<?php\n\nuse Koldy\\Db;\n\n"
			. "class {$class} extends \\Koldy\\Db\\Migration\n{\n\n"
			. "\tpublic function up(): void\n\t{\n"
			. "\t\tDb::getAdapter('" . self::MIGRATION_CONNECTION . "')->query('CREATE TABLE {$table} (val INTEGER NULL)')->exec();\n"
			. "\t}\n\n"
			. "\tpublic function down(): void\n\t{\n"
			. "\t\tDb::getAdapter('" . self::MIGRATION_CONNECTION . "')->query('DROP TABLE IF EXISTS {$table}')->exec();\n"
			. "\t}\n\n}\n";

		file_put_contents($this->migrationsDir() . "/{$timestamp}_{$name}.php", $php);

		return [$timestamp, $table];
	}

	private function tableExists(string $table): bool
	{
		try {
			self::adapter()->query('SELECT COUNT(*) AS cnt FROM ' . $table)->fetchAll();
			return true;
		} catch (\Throwable) {
			return false;
		}
	}

	private function trackingRowCount(): int
	{
		$rows = self::adapter()->query('SELECT COUNT(*) AS cnt FROM koldy_migration')->fetchAll();
		return (int)$rows[0]['cnt'];
	}

	// ────────── migrate ──────────

	public function testMigrateCreatesTrackingTableAndAppliesMigration(): void
	{
		[, $table] = $this->writeTableMigration();

		Manager::migrate();

		$this->assertTrue($this->tableExists('koldy_migration'), 'the tracking table is created automatically');
		$this->assertSame(1, $this->trackingRowCount(), 'the executed migration is recorded');
		$this->assertTrue($this->tableExists($table), 'the migration up() ran and created its table');

		self::adapter()->query('DROP TABLE IF EXISTS ' . $table)->exec();
	}

	public function testMigrateIsIdempotentOnSecondRun(): void
	{
		[, $table] = $this->writeTableMigration();

		Manager::migrate();
		$this->assertSame(1, $this->trackingRowCount());

		// running again must not re-execute the already-recorded migration
		Manager::migrate();
		$this->assertSame(1, $this->trackingRowCount(), 'an already executed migration is not run again');

		self::adapter()->query('DROP TABLE IF EXISTS ' . $table)->exec();
	}

	public function testMigrateRunsMultipleMigrationsInTimestampOrder(): void
	{
		[, $tableA] = $this->writeTableMigration();
		[, $tableB] = $this->writeTableMigration();

		Manager::migrate();

		$this->assertSame(2, $this->trackingRowCount());
		$this->assertTrue($this->tableExists($tableA));
		$this->assertTrue($this->tableExists($tableB));

		self::adapter()->query('DROP TABLE IF EXISTS ' . $tableA)->exec();
		self::adapter()->query('DROP TABLE IF EXISTS ' . $tableB)->exec();
	}

	// ────────── rollBack ──────────

	public function testRollBackRunsDownAndRemovesTrackingRow(): void
	{
		[, $table] = $this->writeTableMigration();

		Manager::migrate();
		$this->assertTrue($this->tableExists($table));
		$this->assertSame(1, $this->trackingRowCount());

		Manager::rollBack();

		$this->assertFalse($this->tableExists($table), 'down() dropped the table');
		$this->assertSame(0, $this->trackingRowCount(), 'the tracking row is removed on rollback');
	}

	public function testRollBackWithInvalidStepsThrows(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		Manager::rollBack(0);
	}

	// ────────── createMigration (template) ──────────

	public function testCreateMigrationWritesTemplateFile(): void
	{
		Manager::createMigration('add something here');

		$files = glob($this->migrationsDir() . '/*.php') ?: [];
		$this->assertCount(1, $files, 'createMigration() writes exactly one migration file');

		$content = (string)file_get_contents($files[0]);
		$this->assertStringContainsString('extends Migration', $content);
		$this->assertStringContainsString('public function up(): void', $content);
		$this->assertStringContainsString('public function down(): void', $content);
	}

	// ────────── invalid input ──────────

	public function testInvalidMigrationFilenameThrows(): void
	{
		// a file name without exactly one underscore is rejected by the Manager
		file_put_contents($this->migrationsDir() . '/invalidname.php', "<?php\n");

		$this->expectException(MigrationException::class);
		Manager::migrate();
	}

	private static function rrmdir(string $dir): void
	{
		if ($dir === '' || !is_dir($dir)) {
			return;
		}

		foreach (scandir($dir) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$path = $dir . DIRECTORY_SEPARATOR . $entry;
			if (is_dir($path)) {
				self::rrmdir($path . DIRECTORY_SEPARATOR);
			} else {
				unlink($path);
			}
		}

		rmdir($dir);
	}

}
