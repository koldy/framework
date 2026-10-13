<?php

declare(strict_types=1);

namespace Tests\DbUnit\Model;

/**
 * Composite-key Model behaviour against a real MariaDB server
 */
class MariaDBCompositeKeyTest extends CompositeKeyTestCase
{

	protected static function adapterKey(): string
	{
		return 'mariadb';
	}

}
