<?php declare(strict_types=1);

namespace Koldy\Db\Query;

use Koldy\Db\{
  Where, Query, Expr
};

/**
 * The UPDATE query builder.
 *
 * @link http://koldy.net/docs/database/query-builder#update
 */
class Update extends Where
{

    use Statement;

    /**
     * The table name on which UPDATE will be performed
     * @var string|null
     */
    protected string | null $table = null;

    /**
     * The key-value pairs of fields and values to be set
     * @var array
     */
    protected array $what = [];

    /**
     * @var array
     */
    protected array $orderBy = [];

	/**
	 * @param string|null $table
	 * @param array|null $values [optional] auto set values in this query
	 * @param string|null $adapter
	 *
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Exception
	 * @link http://koldy.net/docs/database/query-builder#update
	 */
    public function __construct(string $table = null, array $values = null, string $adapter = null)
    {
        $this->table = $table;
        $this->setAdapter($adapter);

        if ($values !== null) {
            $this->setValues($values);
        }
    }

    /**
     * @param string $table
     *
     * @return Update
     */
    public function table(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Set field to be updated
     *
     * @param string $field
     * @param int|float|bool|string $value
     *
     * @return static
     */
    public function set(string $field, int | float | bool | string | Expr $value): static
    {
        $this->what[$field] = $value;
        return $this;
    }

    /**
     * Set the values to be updated
     *
     * @param array $values
     *
     * @return static
     */
    public function setValues(array $values): static
    {
        $this->what = $values;
        return $this;
    }

	/**
	 * Add field to ORDER BY
	 *
	 * @param string $field
	 * @param string|null $direction
	 *
	 * @return static
	 * @throws Exception
	 */
    public function orderBy(string $field, string $direction = null): static
    {
        if ($direction === null) {
            $direction = 'ASC';
        } else {
            $direction = strtoupper($direction);
        }

        $this->orderBy[] = [
          'field' => $field,
          'direction' => $direction
        ];

        return $this;
    }

    /**
     * Reset ORDER BY (remove ORDER BY)
     * @return static
     */
    public function resetOrderBy(): static
    {
        $this->orderBy = [];
        return $this;
    }

    /**
     * Increment numeric field's value in database
     *
     * @param string $field
     * @param int $howMuch
     *
     * @return static
     */
    public function increment(string $field, int $howMuch = 1): static
    {
        return $this->set($field, new Expr("{$field} + {$howMuch}"));
    }

    /**
     * Decrement numeric field's value in database
     *
     * @param string $field
     * @param int $howMuch
     *
     * @return static
     */
    public function decrement(string $field, int $howMuch = 1): static
    {
        return $this->set($field, new Expr("{$field} - {$howMuch}"));
    }

    /**
     * Get the query
     * @return Query
     * @throws Exception
     * @throws \Koldy\Db\Exception
     */
    public function getQuery(): Query
    {
        if (count($this->what) == 0) {
            throw new Exception('Can not build UPDATE query, SET is not defined');
        }

        if ($this->table === null) {
            throw new Exception('Can not build UPDATE query when table name is not set');
        }

        $sql = "UPDATE {$this->table}\nSET\n";

        foreach ($this->what as $field => $value) {
            $sql .= "\t{$field} = ";
            if ($value instanceof Expr) {
                $sql .= "{$value},\n";
            } else {
                //$key = $this->bind($field, $value);
	            $key = $this->getBindings()->makeAndSet($field, $value);
                $sql .= ":{$key},\n";
            }
        }

        $sql = substr($sql, 0, -2);

        if ($this->hasWhere()) {
            $sql .= "\nWHERE{$this->getWhereSql()}";
        }

        if (count($this->orderBy) > 0) {
            $sql .= "\nORDER BY";
            foreach ($this->orderBy as $r) {
                $sql .= "\n\t{$r['field']} {$r['direction']},";
            }
            $sql = substr($sql, 0, -1);
        }

        return new Query($sql, $this->getBindings(), $this->getAdapterConnection());
    }

    /**
     * Get how many rows was updated
     *
     * @return int
     * @throws Exception
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
