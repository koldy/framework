<?php

declare(strict_types=1);

namespace Tests\DbUnit\Model;

/**
 * Composite-key Model behaviour against in-memory SQLite
 */
class SqliteCompositeKeyTest extends CompositeKeyTestCase
{

	protected static function adapterKey(): string
	{
		return 'sqlite';
	}

}
