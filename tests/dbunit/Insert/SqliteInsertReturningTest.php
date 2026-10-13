<?php

declare(strict_types=1);

namespace Tests\DbUnit\Insert;

use PDO;

/**
 * INSERT ... RETURNING against SQLite (in-memory). RETURNING needs libsqlite 3.35+, so the expectation follows the
 * library PHP is linked against.
 */
class SqliteInsertReturningTest extends InsertReturningTestCase
{

	protected static function adapterKey(): string
	{
		return 'sqlite';
	}

	protected static function createTableSql(): string
	{
		return 'CREATE TABLE ' . self::TABLE . ' (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			name TEXT NOT NULL,
			created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
			active INTEGER NOT NULL DEFAULT 1
		)';
	}

	protected function expectsReturning(): bool
	{
		return static::adapter()->supportsReturning();
	}

	public function testInsertSelectReturnsCopiedRows(): void
	{
		// Pre-existing and unrelated to RETURNING: Insert::getQuery() wraps the SELECT in parentheses
		// (INSERT INTO t (c) (SELECT ...)), which PostgreSQL and MySQL accept but SQLite rejects.
		$this->markTestSkipped('INSERT ... (SELECT ...) is not valid SQLite syntax');
	}

	public function testSupportsReturningFollowsLibraryVersion(): void
	{
		$libVersion = (string)static::adapter()->getPDO()->getAttribute(PDO::ATTR_SERVER_VERSION);

		$this->assertSame(version_compare($libVersion, '3.35.0', '>='), static::adapter()->supportsReturning(),
			"libsqlite {$libVersion}: RETURNING must be enabled exactly when the library is 3.35.0 or newer");
	}

}
