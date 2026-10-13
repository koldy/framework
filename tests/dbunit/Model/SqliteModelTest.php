<?php

declare(strict_types=1);

namespace Tests\DbUnit\Model;

/**
 * ActiveRecord Model against in-memory SQLite (RETURNING depends on the linked libsqlite version)
 */
class SqliteModelTest extends ModelTestCase
{

	protected static function adapterKey(): string
	{
		return 'sqlite';
	}

	protected function expectsReturning(): bool
	{
		return static::adapter()->supportsReturning();
	}

}
