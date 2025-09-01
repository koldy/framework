<?php declare(strict_types=1);

namespace Koldy\Db\Query;

use Koldy\Db\{Query, Where};

/**
 * The DELETE query builder
 *
 * @link http://koldy.net/docs/database/query-builder#delete
 */
class Delete extends Where
{

	use Statement;

	/**
	 * The table name on which DELETE will be performed
	 * @var string|null
	 */
	protected string|null $table = null;

	/**
	 * @var string|null
	 */
	protected string|null $adapter = null;

	/**
	 * @param string|null $table
	 * @param string|null $adapter
	 *
	 * @link http://koldy.net/docs/database/query-builder#delete
	 */
	public function __construct(string|null $table = null, string|null $adapter = null)
	{
		$this->table = $table;
		$this->adapter = $adapter;
	}

	/**
	 * @param string $table
	 *
	 * @return static
	 */
	public function from(string $table): static
	{
		$this->table = $table;
		return $this;
	}

	/**
	 * Get the query that will be executed
	 * @return Query
	 * @throws Exception
	 * @throws \Koldy\Db\Exception
	 */
	public function getQuery(): Query
	{
		if ($this->table === null) {
			throw new Exception('Unable to build DELETE query when table name is not set');
		}

		$sql = "DELETE FROM {$this->table}";

		if ($this->hasWhere()) {
			$sql .= "\nWHERE{$this->getWhereSql()}";
		}

		return new Query($sql, $this->getBindings(), $this->getAdapterConnection());
	}

	/**
	 * Get how many rows was deleted
	 *
	 * @return int
	 * @throws Exception
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Db\Exception
	 * @throws \Koldy\Exception
	 */
	public function rowCount(): int
	{
		if (!$this->wasExecuted()) {
			$this->exec();
		}

		return $this->getAdapter()->getStatement()->rowCount();
	}

}
