<?php declare(strict_types=1);

namespace Koldy\Db;

abstract class Migration
{

	/**
	 * Will be executed when migrating "up"
	 *
	 * @throws \Koldy\Db\Adapter\Exception
	 * @throws \Koldy\Db\Query\Exception
	 * @throws Exception
	 */
	abstract public function up(): void;

	/**
	 * Will be executed when rolling back "down"
	 *
	 * @throws \Koldy\Db\Adapter\Exception
	 * @throws \Koldy\Db\Query\Exception
	 * @throws Exception
	 */
	abstract public function down(): void;

}
