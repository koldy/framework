<?php declare(strict_types=1);

namespace Koldy\Db\Adapter;

/**
 * MariaDB adapter. MariaDB speaks the MySQL wire protocol, so connection handling is inherited from the MySQL
 * adapter; the difference is that MariaDB 10.5+ supports INSERT ... RETURNING, which lets the framework read back
 * inserted rows (generated primary keys, database defaults) without a second roundtrip.
 *
 * Requires MariaDB 10.5 or newer. Configure it with 'type' => 'mariadb' in configs/database.php; all other keys are
 * the same as for the MySQL adapter.
 */
class MariaDB extends MySQL
{

	/**
	 * @return bool
	 */
	public function supportsReturning(): bool
	{
		return true;
	}

}
