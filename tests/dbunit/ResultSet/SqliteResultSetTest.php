<?php

declare(strict_types=1);

namespace Tests\DbUnit\ResultSet;

/**
 * ResultSet builder against in-memory SQLite. SQLite exposes ILIKE only as a function (ILIKE(mask, value)), not as an
 * infix operator, so ResultSet's operator-form ILIKE search can't run here and the explicit-field search tests skip.
 */
class SqliteResultSetTest extends ResultSetTestCase
{

	protected static function adapterKey(): string
	{
		return 'sqlite';
	}

	protected function ilikeWorksAsOperator(): bool
	{
		return false;
	}

}
