<?php declare(strict_types = 1);

namespace Koldy\Db\Query;

use Koldy\Db\{
  Where, Query
};

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
     * @var string
     */
    protected $table = null;

    /**
     * @var string|null
     */
    protected $adapter = null;

    /**
     * @param string $table
     * @param string|null $adapter
     *
     * @link http://koldy.net/docs/database/query-builder#delete
     */
    public function __construct(string $table = null, string $adapter = null)
    {
        $this->table = $table;
        $this->adapter = $adapter;
    }

    /**
     * @param string $table
     *
     * @return Delete
     */
    public function from(string $table): Delete
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Get the query that will be executed
     * @return Query
     * @throws Exception
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
     */
    public function rowCount(): int
    {
        if (!$this->wasExecuted()) {
            $this->exec();
        }

        return $this->getAdapter()->getStatement()->rowCount();
    }

}
