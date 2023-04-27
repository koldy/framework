<?php declare(strict_types=1);

namespace Koldy\Db\Query;

use Generator;
use InvalidArgumentException;
use stdClass;
use Koldy\Db\{
  Where, Query, Expr
};
use PDO;

/**
 * The SELECT query builder
 *
 * @link http://koldy.net/docs/database/query-builder#select
 */
class Select extends Where
{

    use Statement;

    protected array $fields = [];

    protected array $from = [];

    protected array $joins = [];

    protected array $groupBy = [];

    protected array $having = [];

    protected array $orderBy = [];

    protected stdClass | null $limit = null;

    /**
     * @param string|self|null $table
     * @param string|null $tableAlias
     *
     * @link http://koldy.net/docs/database/query-builder#select
     */
    public function __construct(string | self $table = null, string $tableAlias = null)
    {
        if ($table !== null) {
            $this->from($table, $tableAlias);
        }
    }

    /**
     * Set the table FROM which fields will be fetched
     *
     * @param string|self $table
     * @param string|null $alias
     * @param string|array|null $field one field as string or more fields as array or just '*'
     *
     * @return static
     */
    public function from(string | self $table, string $alias = null, string | array | null $field = null): static
    {
        $this->from[] = [
          'table' => $table,
          'alias' => $alias
        ];

        if ($field !== null) {
            if (is_array($field)) {
                foreach ($field as $fld) {
                    $this->field(($alias ?? $table) . '.' . $fld);
                }
            } else {
                $this->field(($alias ?? $table) . '.' . $field);
            }
        }

        return $this;
    }

	/**
	 * "Inner" join two tables
	 *
	 * @param string $table
	 * @param string|array $firstTableField
	 * @param string|null $operator
	 * @param string|null $secondTableField
	 *
	 * @return static
	 * @example innerJoin('user u', 'u.id', '=', 'r.user_role_id')
	 */
    public function innerJoin(string $table, string | array $firstTableField, string $operator = null, string $secondTableField = null): static
    {
        $this->joins[] = [
          'type' => 'INNER JOIN',
          'table' => $table,
          'first' => $firstTableField,
          'operator' => $operator,
          'second' => $secondTableField
        ];
        return $this;
    }

	/**
	 * "Left" join two tables
	 *
	 * @param string $table
	 * @param string|array $firstTableField
	 * @param string|null $operator
	 * @param string|null $secondTableField
	 *
	 * @return static
	 * @example leftJoin('user u', 'u.id', '=', 'r.user_role_id')
	 * @example leftJoin('user u', [
	 *   ['u.id', '=', 'r.user_role_id'],
	 *   ['u.group_id', '=', 2]
	 * ])
	 */
    public function leftJoin(string $table, string | array $firstTableField, string $operator = null, string $secondTableField = null): static
    {
        $this->joins[] = [
          'type' => 'LEFT JOIN',
          'table' => $table,
          'first' => $firstTableField,
          'operator' => $operator,
          'second' => $secondTableField
        ];
        return $this;
    }

	/**
	 * "Right" join two tables.
	 *
	 * @param string $table
	 * @param string|array $firstTableField
	 * @param string|null $operator
	 * @param string|null $secondTableField
	 *
	 * @return static
	 * @example rightJoin('user u', 'u.id', '=', 'r.user_role_id')
	 */
    public function rightJoin(string $table, string | array $firstTableField, string $operator = null, string $secondTableField = null): static
    {
        $this->joins[] = [
          'type' => 'RIGHT JOIN',
          'table' => $table,
          'first' => $firstTableField,
          'operator' => $operator,
          'second' => $secondTableField
        ];
        return $this;
    }

	/**
	 * "Full" join two tables.
	 *
	 * @param string $table
	 * @param string|array $firstTableField
	 * @param string|null $operator
	 * @param string|null $secondTableField
	 *
	 * @return static
	 */
    public function fullJoin(string $table, string | array $firstTableField, string $operator = null, string $secondTableField = null): static
    {
        $this->joins[] = [
          'type' => 'FULL JOIN',
          'table' => $table,
          'first' => $firstTableField,
          'operator' => $operator,
          'second' => $secondTableField
        ];
        return $this;
    }

	/**
	 * Join two tables. This is alias of innerJoin() method.
	 *
	 * @param string $table
	 * @param string|array $firstTableField
	 * @param string $operator
	 * @param string $secondTableField
	 *
	 * @return static
	 * @example join('user u', 'u.id', '=', 'r.user_role_id')
	 * @see \Koldy\Db\Query\Select::innerJoin()
	 */
    public function join(string $table, string | array $firstTableField, string $operator, string $secondTableField): static
    {
        return $this->innerJoin($table, $firstTableField, $operator, $secondTableField);
    }

	/**
	 * Add one field that will be fetched
	 *
	 * @param string $field
	 * @param string|null $as
	 *
	 * @return static
	 */
    public function field(string $field, string $as = null): static
    {
        $this->fields[] = [
          'name' => $field,
          'as' => $as
        ];

        return $this;
    }

	/**
	 * Removes the field that was already added to Select statement
	 *
	 * @param string|null $field
	 * @param string|null $as
	 *
	 * @return static
	 */
	public function removeField(string $field = null, string $as = null): static
	{
		if ($field === null && $as === null) {
			throw new InvalidArgumentException('Received null for parameter 1 and 2; removeField() method must be called with at least one parameter or both');
		}

		$count = count($this->fields);

		for ($index = null, $i = 0; $i < $count && $index === null; $i++) {
			if ($field !== null && $as !== null) {
				if ($this->fields[$i]['name'] === $field && $this->fields[$i]['as'] === $as) {
					$index = $i;
				}
			} else if ($field !== null && $as === null) {
				if ($this->fields[$i]['name'] === $field) {
					$index = $i;
				}
			} else if ($field === null && $as !== null) {
				if ($this->fields[$i]['as'] === $as) {
					$index = $i;
				}
			}
		}

		if ($index !== null) {
			unset($this->fields[$i]);
			$this->fields = array_values($this->fields);
		}

		return $this;
	}

    /**
     * Add fields to fetch by passing array of fields
     *
     * @param array $fields
     * @param null|string $alias
     *
     * @return static
     */
    public function fields(array $fields, string $alias = null): static
    {
        $alias = ($alias === null) ? '' : "{$alias}.";

        foreach ($fields as $field => $as) {
            if (is_numeric($field)) {
                $this->field($alias . $as);
            } else {
                $this->field($alias . $field, $as);
            }
        }

        return $this;
    }

    /**
     * Reset all fields that will be fetched
     * @return static
     */
    public function resetFields(): static
    {
        $this->fields = [];
        return $this;
    }

    /**
     * Get the array of fields that were added to SELECT query
     * @return array
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Add field to GROUP BY
     *
     * @param string $field
     *
     * @return static
     */
    public function groupBy(string $field): static
    {
        $this->groupBy[] = [
          'field' => $field
        ];
        return $this;
    }

    /**
     * Reset GROUP BY (remove GROUP BY)
     * @return static
     */
    public function resetGroupBy(): static
    {
        $this->groupBy = [];
        return $this;
    }

	/**
	 * Removes previously set field through groupBy() method
	 *
	 * @param string $field
	 *
	 * @return static
	 */
	public function removeGroupBy(string $field): static
	{
		$count = count($this->groupBy);

		for ($index = null, $i = 0; $i < $count && $index === null; $i++) {
			if ($this->groupBy[$i]['field'] === $field) {
				$index = $i;
			}
		}

		if ($index !== null) {
			unset($this->groupBy[$i]);
			$this->groupBy = array_values($this->groupBy);
		}

		return $this;
	}

    /**
     * Add HAVING to your SELECT query
     *
     * @param string|Expr $field
     * @param string|null $operator
     * @param int|float|string|bool|Expr|null $value
     *
     * @return static
     */
    public function having(string | Expr $field, string $operator = null, int | float | string | bool | Expr | null $value = null): static
    {
        $this->having[] = [
          'link' => 'AND',
          'field' => $field,
          'operator' => $operator,
          'value' => $value
        ];

        return $this;
    }

    /**
     * Add HAVING with OR operator
     *
     * @param string|Expr $field
     * @param string|null $operator
     * @param int|float|string|bool|Expr|null $value
     *
     * @return static
     */
    public function orHaving(string | Expr $field, string $operator = null, int | float | string | bool | Expr | null $value = null): static
    {
        $this->having[] = [
          'link' => 'OR',
          'field' => $field,
          'operator' => $operator,
          'value' => $value
        ];

        return $this;
    }

    /**
     * Reset HAVING statement
     * @return static
     */
    public function resetHaving(): static
    {
        $this->having = [];
        return $this;
    }

	/**
	 * Add field to ORDER BY
	 *
	 * @param string $field
	 * @param string|null $direction
	 *
	 * @return static
	 */
    public function orderBy(string $field, string $direction = null): static
    {
        if ($direction === null) {
            $direction = 'ASC';
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
	 * Removes the order by directive by previously set field through orderBy() method
	 *
	 * @param string $field
	 *
	 * @return static
	 */
	public function removeOrderBy(string $field): static
	{
		$count = count($this->orderBy);

		for ($index = null, $i = 0; $i < $count && $index === null; $i++) {
			if ($this->orderBy[$i]['field'] === $field) {
				$index = $i;
			}
		}

		if ($index !== null) {
			unset($this->orderBy[$i]);
			$this->orderBy = array_values($this->orderBy);
		}

		return $this;
	}

    /**
     * Set the LIMIT on query results
     *
     * @param int $start
     * @param int $howMuch
     *
     * @return static
     */
    public function limit(int $start, int $howMuch): static
    {
        $this->limit = new stdClass;
        $this->limit->start = $start;
        $this->limit->howMuch = $howMuch;
        return $this;
    }

    /**
     * Limit the results by "page"
     *
     * @param int $number
     * @param int $limitPerPage
     *
     * @return static
     */
    public function page(int $number, int $limitPerPage): static
    {
        return $this->limit(($number - 1) * $limitPerPage, $limitPerPage);
    }

    /**
     * Reset LIMIT (remove the LIMIT)
     * @return static
     */
    public function resetLimit(): static
    {
        $this->limit = null;
        return $this;
    }

    /**
     * Get the query string prepared for PDO
     * @return Query
     * @throws Exception
     * @throws \Koldy\Db\Exception
     * @throws \Koldy\Exception
     */
    public function getQuery(): Query
    {
    	// TODO: Bindings could be defined only locally in this method because it has to be regenerated on every call

        if (count($this->from) == 0) {
            throw new Exception('Can not build SELECT query, there is no FROM table defined');
        }

        $query = "SELECT\n";

        if (sizeof($this->fields) == 0) {
            $query .= "\t*";
        } else {
            foreach ($this->fields as $fld) {
                $field = $fld['name'];
                $as = $fld['as'];

                $query .= "\t{$field}";
                if ($as !== null) {
                    $query .= " as {$as}";
                }

                $query .= ",\n";
            }

            $query = substr($query, 0, -2);
        }

        $query .= "\nFROM";
        foreach ($this->from as $from) {
            if ($from['table'] instanceof self || $from['table'] instanceof static) {
                /* @var self|static $subSelect */
                $subSelect = $from['table'];
                $subSql = $subSelect->getSQL();
                $subSql = str_replace("\n", "\n\t", $subSql);
                $query .= " (\n\t{$subSql}\n) {$from['alias']}\n";

                $subSelectBindings = $subSelect->getBindings();
                if (!$subSelectBindings->isEmpty()) {
                    $this->getBindings()->addBindingsFromInstance($subSelectBindings);
                }
            } else {
                $query .= "\n\t{$from['table']}";
                if ($from['alias'] !== null) {
                    $query .= " as {$from['alias']},";
                } else {
                    $query .= ',';
                }
            }
        }
        $query = substr($query, 0, -1);

        foreach ($this->joins as $join) {
            $query .= "\n\t{$join['type']} {$join['table']} ON ";

            if (is_array($join['first'])) {
                foreach ($join['first'] as $joinArg) {
                    if ($joinArg instanceof Expr) {
                        $query .= "{$joinArg} AND ";

                    } else if (is_array($joinArg) && count($joinArg) == 2) {
                        $query .= "{$joinArg[0]} = {$joinArg[1]} AND ";

                    } else if (is_array($joinArg) && count($joinArg) == 3) {
                        $query .= "{$joinArg[0]} {$joinArg[1]} {$joinArg[2]} AND ";

                    } else if (is_array($joinArg) && count($joinArg) == 4) {
                        if (str_ends_with($query, ' AND ')) {
                            $query = substr($query, 0, -5);
                        }

                        $query .= " {$joinArg[0]} {$joinArg[1]} {$joinArg[2]} {$joinArg[3]} AND ";

                    } else {
                        throw new Exception('Unknown JOIN argument');

                    }
                }

                $query = substr($query, 0, -5);
            } else {
                if ($join['operator'] === null) {
                    throw new Exception("JOIN operator can't be null; see: left-table={$join['first']} operator=NULL right-table=" . ($join['second'] === null ? 'NULL' : $join['second']));
                }

                if ($join['second'] === null) {
                    throw new Exception("Second parameter in JOIN statement can't be null; see: left-table={$join['first']} operator={$join['operator']} right-table=NULL");
                }

                $query .= "{$join['first']} {$join['operator']} {$join['second']}";
            }
        }

        if ($this->hasWhere()) {
            $query .= "\nWHERE\n\t" . trim($this->getWhereSql());
        }

        if (sizeof($this->groupBy) > 0) {
            $query .= "\nGROUP BY";
            foreach ($this->groupBy as $r) {
                $query .= " {$r['field']},";
            }
            $query = substr($query, 0, -1);
        }

        $sizeofHaving = count($this->having);
        if ($sizeofHaving > 0) {
            $query .= "\nHAVING";
            if ($sizeofHaving == 1) {
                $nl = ' ';
            } else {
                $nl = "\n\t";
            }

            foreach ($this->having as $index => $having) {
                $link = ($index > 0) ? "{$having['link']} " : '';
                if ($having['value'] instanceof Expr) {
                    $query .= "{$nl}{$link}{$having['field']} {$having['operator']} {$having['value']}";
                } else {
	                //$bindName = $this->bind($having['field'], $having['value'], 'h');
	                $bindName = $this->getBindings()->makeAndSet('h' . $having['field'], $having['value']);
	                $query .= "{$nl}{$link}{$having['field']} {$having['operator']} :{$bindName}";
                }
            }
        }

        if (sizeof($this->orderBy) > 0) {
            $query .= "\nORDER BY";
            foreach ($this->orderBy as $r) {
                $query .= "\n\t{$r['field']} {$r['direction']},";
            }
            $query = substr($query, 0, -1);
        }

        if ($this->limit !== null) {
            $query .= "\nLIMIT {$this->limit->howMuch} OFFSET {$this->limit->start}";
        }

        return new Query($query, $this->getBindings(), $this->getAdapterConnection());
    }

    /**
     * Fetch all records by this query
     * @return array
     * @throws Exception
     * @throws \Koldy\Exception
     */
    public function fetchAll(): array
    {
        if (!$this->wasExecuted()) {
            $this->exec();
        }

        return $this->getAdapter()->getStatement()->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch all records by getting Generator back
     *
     * @return Generator
     * @throws Exception
     * @throws \Koldy\Exception
     */
    public function fetchAllGenerator(): Generator
    {
        if (!$this->wasExecuted()) {
            $this->exec();
        }

        $statement = $this->getAdapter()->getStatement();

        while ($record = $statement->fetch(PDO::FETCH_ASSOC)) {
            yield $record;
        }

        $statement->closeCursor();
    }

    /**
     * Fetch all records as array of objects
     *
     * @param string|null $class the name of class on which you want the instance of - class has to be able to accept array in constructor
     *
     * @return array
     * @throws Exception
     * @throws \Koldy\Exception
     */
    public function fetchAllObj(string $class = null): array
    {
        if (!$this->wasExecuted()) {
            $this->exec();
        }

        if ($class === null) {
            return $this->getAdapter()->getStatement()->fetchAll(PDO::FETCH_OBJ);
        } else {
            $objects = [];
            foreach ($this->fetchAll() as $record) {
                $objects[] = new $class($record);
            }

            return $objects;
        }
    }

    /**
     * Fetch all records from the executed SELECT statement and get the Generator back.
     *
     * @param string|null $class the name of class on which you want the instance of - class has to be able to accept array in constructor
     *
     * @return Generator
     * @throws Exception
     * @throws \Koldy\Exception
     */
    public function fetchAllObjGenerator(string $class = null): Generator
    {
        if (!$this->wasExecuted()) {
            $this->exec();
        }

        $statement = $this->getAdapter()->getStatement();

        if ($class === null) {
            while ($record = $statement->fetch(PDO::FETCH_OBJ)) {
                yield $record;
            }
        } else {
            while ($record = $statement->fetch(PDO::FETCH_ASSOC)) {
                yield new $class($record);
            }
        }

        $statement->closeCursor();
    }

    /**
     * Fetch all records and return array of values from each row from given field name.
     *
     * @param string $field
     *
     * @return array
     * @throws Exception
     * @throws \Koldy\Exception
     */
    public function fetchAllOf(string $field): array
    {
        $array = [];
        foreach ($this->fetchAllGenerator() as $record) {
            $array[] = $record[$field] ?? null;
        }

        return $array;
    }

    /**
     * Fetch all records and return Generator of values from each row from given field name.
     *
     * @param string $field
     *
     * @return Generator
     * @throws Exception
     * @throws \Koldy\Exception
     */
    public function fetchAllOfGenerator(string $field): Generator
    {
        foreach ($this->fetchAllGenerator() as $index => $record) {
            yield $index => $record[$field] ?? null;
        }
    }

    /**
     * Fetch only first record as object or return null if there is no records
     * @return array|null
     * @throws Exception
     * @throws \Koldy\Exception
     */
    public function fetchFirst(): ?array
    {
        if (!$this->wasExecuted()) {
            $this->exec();
        }

        $this->resetLimit()->limit(0, 1);
        $results = $this->fetchAll();
        return count($results) > 0 ? $results[0] : null;
    }

	/**
	 * Fetch only first record as object
	 *
	 * @param string|null $class
	 *
	 * @return null|object
	 * @throws Exception
	 * @throws \Koldy\Exception
	 */
    public function fetchFirstObj(string $class = null): ?object
    {
        if (!$this->wasExecuted()) {
            $this->exec();
        }

        $data = $this->fetchFirst();

        if ($data === null) {
            return null;
        }

        if ($class == null) {
            $obj = new \stdClass();

            foreach ($data as $field => $value) {
                $obj->$field = $value;
            }

            return $obj;
        } else {
            return new $class($data);
        }
    }

    public function __clone()
    {
        foreach ($this->where as $index => $where) {
            if (isset($where['field']) && $where['field'] instanceof Where) {
                // reset bindings in nested Where statements
                $this->where[$index]['field']->resetBindings();
            }
        }

        $this->resetBindings();
        $this->resetLastQuery();
    }

}
