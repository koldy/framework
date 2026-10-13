<?php

declare(strict_types=1);

namespace Tests\DbUnit\Adapter;

use Koldy\Db;
use Koldy\Db\Adapter\LanguageException;
use Koldy\Db\Adapter\PostgreSQL;
use Tests\DbUnit\Support\Schema;

/**
 * Adapter behaviour against a real PostgreSQL server, plus PostgreSQL-only concerns: isBoolTrue(), booleans coming
 * back as real PHP bools through RETURNING, and the schema/search_path handling.
 */
class PostgreSQLAdapterTest extends AdapterTestCase
{

	protected static function adapterKey(): string
	{
		return 'postgres';
	}

	protected function expectsSupportsReturning(): ?bool
	{
		return true;
	}

	protected function lastInsertIdKeyName(): ?string
	{
		return 'koldy_dbunit_items_id_seq';
	}

	// ────────── isBoolTrue ──────────

	public function testIsBoolTrueReturnsNullForNull(): void
	{
		$this->assertNull(PostgreSQL::isBoolTrue(null));
	}

	public function testIsBoolTruePassesRealBoolsThrough(): void
	{
		$this->assertTrue(PostgreSQL::isBoolTrue(true));
		$this->assertFalse(PostgreSQL::isBoolTrue(false));
	}

	public function testIsBoolTrueForTruthyStrings(): void
	{
		$this->assertTrue(PostgreSQL::isBoolTrue('true'));
		$this->assertTrue(PostgreSQL::isBoolTrue('TRUE'));
		$this->assertTrue(PostgreSQL::isBoolTrue('1'));
	}

	public function testIsBoolTrueForFalsyStrings(): void
	{
		$this->assertFalse(PostgreSQL::isBoolTrue('false'));
		$this->assertFalse(PostgreSQL::isBoolTrue('0'));
		$this->assertFalse(PostgreSQL::isBoolTrue(''));
	}

	public function testIsBoolTrueThrowsOnUnknownString(): void
	{
		$this->expectException(LanguageException::class);
		PostgreSQL::isBoolTrue('yes');
	}

	public function testReturningBooleanComesBackAsRealBool(): void
	{
		$insert = static::adapter()->insert(Schema::ITEMS, ['name' => 'Booly'])->returning('active');
		$insert->exec();
		$row = $insert->getReturnedRow();

		$this->assertIsBool($row['active'], 'RETURNING hands boolean columns back as real PHP bool');
		$this->assertTrue(PostgreSQL::isBoolTrue($row['active']));
	}

	// ────────── schema / search_path ──────────

	public function testSchemaSearchPathIsApplied(): void
	{
		$adapter = static::adapter();
		$adapter->query('DROP SCHEMA IF EXISTS koldy_dbunit_sc CASCADE')->exec();
		$adapter->query('CREATE SCHEMA koldy_dbunit_sc')->exec();

		try {
			$config = $adapter->getConfig();
			$config['schema'] = 'koldy_dbunit_sc';

			// register the scoped adapter so its own queries resolve back to it (Query looks the adapter up by key)
			$scoped = new PostgreSQL($config, 'pg_scoped');
			Db::addAdapter('pg_scoped', $scoped);

			// unqualified CREATE must land in the search_path schema
			$scoped->query('CREATE TABLE koldy_dbunit_scoped (id INT)')->exec();

			$rows = $adapter->query(
				"SELECT COUNT(*) AS c FROM information_schema.tables
				 WHERE table_schema = 'koldy_dbunit_sc' AND table_name = 'koldy_dbunit_scoped'"
			)->fetchAll();

			$this->assertSame(1, (int)$rows[0]['c'], 'SET search_path routed the unqualified CREATE into the schema');
		} finally {
			Db::removeAdapter('pg_scoped');
			$adapter->query('DROP SCHEMA IF EXISTS koldy_dbunit_sc CASCADE')->exec();
		}
	}

}
