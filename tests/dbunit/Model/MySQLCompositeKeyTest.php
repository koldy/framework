<?php

declare(strict_types=1);

namespace Tests\DbUnit\Model;

/**
 * Composite-key Model behaviour against a real MySQL server
 */
class MySQLCompositeKeyTest extends CompositeKeyTestCase
{

	protected static function adapterKey(): string
	{
		return 'mysql';
	}

}
