<?php

declare(strict_types=1);

namespace Tests\DbUnit\Model;

/**
 * Composite-key Model behaviour against a real PostgreSQL server
 */
class PostgreSQLCompositeKeyTest extends CompositeKeyTestCase
{

	protected static function adapterKey(): string
	{
		return 'postgres';
	}

}
