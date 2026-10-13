<?php

declare(strict_types=1);

/**
 * Database connections used by the dbunit suite. Defaults match tests/dbunit/compose.yaml; every value can be
 * overridden through environment variables, e.g. KOLDY_DBUNIT_PG_PORT=5432 to run against a local PostgreSQL.
 */

$env = static function (string $name, string $default): string {
	$value = getenv($name);
	return ($value === false || $value === '') ? $default : $value;
};

$server = static fn (string $prefix, string $defaultPort): array => [
	'host' => $env("KOLDY_DBUNIT_{$prefix}_HOST", '127.0.0.1'),
	'port' => (int)$env("KOLDY_DBUNIT_{$prefix}_PORT", $defaultPort),
	'username' => $env("KOLDY_DBUNIT_{$prefix}_USER", 'koldy'),
	'password' => $env("KOLDY_DBUNIT_{$prefix}_PASSWORD", 'koldy'),
	'database' => $env("KOLDY_DBUNIT_{$prefix}_DATABASE", 'koldy'),
	'persistent' => false,
];

return [
	'postgres' => ['type' => 'postgres'] + $server('PG', '54329'),
	'mariadb' => ['type' => 'mariadb'] + $server('MARIADB', '33069'),
	'mysql' => ['type' => 'mysql'] + $server('MYSQL', '33068'),
	'sqlite' => ['type' => 'sqlite', 'path' => ':memory:'],
];
