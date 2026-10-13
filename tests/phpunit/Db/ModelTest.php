<?php

declare(strict_types=1);

namespace Tests\PhpUnit\Db;

use Koldy\Db\Model;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Model defaults that don't need a database server. Real-server behaviour of create()/save() lives in the dbunit
 * suite.
 */
class ModelTest extends TestCase
{

	public function testReturningIsOptInByDefault(): void
	{
		// INSERT ... RETURNING changes what a freshly inserted model holds (typed values, defaults, needs SELECT
		// privilege on PostgreSQL), so models must ask for it explicitly; the default keeps the lastInsertId() path
		$property = new ReflectionProperty(Model::class, 'returning');

		$this->assertFalse($property->getDefaultValue());
	}

}
