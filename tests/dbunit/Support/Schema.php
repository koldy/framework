<?php

declare(strict_types=1);

namespace Tests\DbUnit\Support;

use Koldy\Db\Adapter\AbstractAdapter;

/**
 * Dialect-aware DDL for the tables used across the dbunit suite. Centralizes the per-engine CREATE TABLE so each
 * feature test only has to supply an adapter key; the differences between PostgreSQL, MySQL/MariaDB and SQLite (auto
 * increment syntax, boolean type, timestamp defaults, binary type) live here in one place.
 *
 * Tables (all prefixed koldy_dbunit_ so they never collide with a real schema):
 *   - categories: id, name
 *   - items:      id, category_id, name, price, active, created_at, payload  (category_id is a plain nullable int, no
 *                 FK constraint, so create/drop order is free on every engine)
 *   - memberships: user_id, group_id, role  with a composite (user_id, group_id) primary key
 */
class Schema
{

	public const string CATEGORIES = 'koldy_dbunit_categories';
	public const string ITEMS = 'koldy_dbunit_items';
	public const string MEMBERSHIPS = 'koldy_dbunit_memberships';

	/**
	 * Which SQL dialect family does this adapter speak: 'pgsql', 'mysql' (also MariaDB) or 'sqlite'
	 */
	private static function family(AbstractAdapter $adapter): string
	{
		return match ($adapter->getType()) {
			'postgres', 'postgresql' => 'pgsql',
			'mysql', 'mariadb' => 'mysql',
			'sqlite' => 'sqlite',
			default => throw new \RuntimeException('Unsupported adapter type for dbunit schema: ' . $adapter->getType()),
		};
	}

	/**
	 * Create all three tables (drops them first so a test always starts from a clean, empty schema)
	 */
	public static function createAll(AbstractAdapter $adapter): void
	{
		self::dropAll($adapter);
		self::createCategories($adapter);
		self::createItems($adapter);
		self::createMemberships($adapter);
	}

	/**
	 * Drop all three tables if they exist
	 */
	public static function dropAll(AbstractAdapter $adapter): void
	{
		self::drop($adapter, self::MEMBERSHIPS, self::ITEMS, self::CATEGORIES);
	}

	/**
	 * Drop the given tables if they exist
	 */
	public static function drop(AbstractAdapter $adapter, string ...$tables): void
	{
		foreach ($tables as $table) {
			$adapter->query('DROP TABLE IF EXISTS ' . $table)->exec();
		}
	}

	public static function createCategories(AbstractAdapter $adapter): void
	{
		$sql = match (self::family($adapter)) {
			'pgsql' => 'CREATE TABLE ' . self::CATEGORIES . ' (
				id SERIAL PRIMARY KEY,
				name VARCHAR(100) NOT NULL
			)',
			'mysql' => 'CREATE TABLE ' . self::CATEGORIES . ' (
				id INT UNSIGNED NOT NULL AUTO_INCREMENT,
				name VARCHAR(100) NOT NULL,
				PRIMARY KEY(id)
			) ENGINE=InnoDB',
			'sqlite' => 'CREATE TABLE ' . self::CATEGORIES . ' (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				name VARCHAR(100) NOT NULL
			)',
		};

		$adapter->query($sql)->exec();
	}

	public static function createItems(AbstractAdapter $adapter): void
	{
		$sql = match (self::family($adapter)) {
			'pgsql' => 'CREATE TABLE ' . self::ITEMS . ' (
				id SERIAL PRIMARY KEY,
				category_id INTEGER NULL,
				name VARCHAR(100) NOT NULL,
				price NUMERIC(10,2) NULL,
				active BOOLEAN NOT NULL DEFAULT true,
				created_at TIMESTAMP NOT NULL DEFAULT now(),
				payload BYTEA NULL
			)',
			'mysql' => 'CREATE TABLE ' . self::ITEMS . ' (
				id INT UNSIGNED NOT NULL AUTO_INCREMENT,
				category_id INT NULL,
				name VARCHAR(100) NOT NULL,
				price DECIMAL(10,2) NULL,
				active TINYINT(1) NOT NULL DEFAULT 1,
				created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				payload BLOB NULL,
				PRIMARY KEY(id)
			) ENGINE=InnoDB',
			'sqlite' => 'CREATE TABLE ' . self::ITEMS . ' (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				category_id INTEGER NULL,
				name VARCHAR(100) NOT NULL,
				price NUMERIC NULL,
				active INTEGER NOT NULL DEFAULT 1,
				created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				payload BLOB NULL
			)',
		};

		$adapter->query($sql)->exec();
	}

	public static function createMemberships(AbstractAdapter $adapter): void
	{
		$sql = match (self::family($adapter)) {
			'pgsql', 'sqlite' => 'CREATE TABLE ' . self::MEMBERSHIPS . ' (
				user_id INTEGER NOT NULL,
				group_id INTEGER NOT NULL,
				role VARCHAR(50) NOT NULL,
				PRIMARY KEY(user_id, group_id)
			)',
			'mysql' => 'CREATE TABLE ' . self::MEMBERSHIPS . ' (
				user_id INT NOT NULL,
				group_id INT NOT NULL,
				role VARCHAR(50) NOT NULL,
				PRIMARY KEY(user_id, group_id)
			) ENGINE=InnoDB',
		};

		$adapter->query($sql)->exec();
	}

}
