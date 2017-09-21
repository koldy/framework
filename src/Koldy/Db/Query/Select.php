<?php declare(strict_types=1);

namespace Koldy\Db\Query;

use Koldy\Db;
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

    /**
     * @var array
     */
    protected $fields = [];

    /**
     * @var array
     */
    protected $from = [];

    /**
     * @var array
     */
    protected $joins = [];

    /**
     * @var array
     */
    protected $groupBy = [];

    /**
     * @var array
     */
    protected $having = [];

    /**
     * @var array
     */
    protected $orderBy = [];

    /**
     * @var null
     */
    protected $limit = null;

    /**
     * @param string|null $table
     * @param string|null $tableAlias
     *
     * @link http://koldy.net/docs/database/query-builder#select
     */
    public function __construct(string $table = null, string $tableAlias = null)
    {
        if ($table !== null) {
            $this->from($table, $tableAlias);
        }
    }

    /**
     * Set the table FROM which fields will be fetched
     *
     * @param string $table
     * @param string $alias
     * @param mixed $field one field as string or more fields as array or just '*'
     *
     * @return Select
     */
    public function from(string $table, string $alias = null, $field = null): Select
    {
        $this->from[] = [
          'table' => $table,
          'alias' => $alias
        ];

        if ($field !== null) {
            if (is_array($field)) {
                foreach ($field as $fld) {
                    $this->field(($alias === null) ? $fld : "{$alias}.{$fld}");
                }
            } else {
                $this->field(($alias === null) ? $field : "{$alias}.{$field}");
            }
        }

        return $this;
    }

    /**
     * Join two tables
     *
     * @param string $table
     * @param string|array $firstTableField
     * @param string $operator
     * @param string $secondTableField
     *
     * @return Select
     * @example innerJoin('user u', 'u.id', '=', 'r.user_role_id')
     */
    public function innerJoin(string $table, $firstTableField, string $operator = null, string $secondTableField = null): Select
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
     * Join two tables
     *
     * @param string $table
     * @param string|array $firstTableField
     * @param string $operator
     * @param string $secondTableField
     *
     * @return Select
     * @example leftJoin('user u', 'u.id', '=', 'r.user_role_id')
     * @example leftJoin('user u', [
     *   ['u.id', '=', 'r.user_role_id'],
     *   ['u.group_id', '=', 2]
     * ])
     */
    public function leftJoin(string $table, $firstTableField, string $operator = null, string $secondTableField = null): Select
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
     * Join two tables
     *
     * @param string $table
     * @param string|array $firstTableField
     * @param string $operator
     * @param string $secondTableField
     *
     * @return Select
     * @example rightJoin('user u', 'u.id', '=', 'r.user_role_id')
     */
    public function rightJoin(string $table, $firstTableField, string $operator = null, string $secondTableField = null): Select
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
     * Join two tables
     *
     * @param string $table
     * @param string $firstTableField
     * @param string $operator
     * @param string $secondTableField
     *
     * @return Select
     * @example join('user u', 'u.id', '=', 'r.user_role_id')
     */
    public function join(string $table, $firstTableField, string $operator, string $secondTableField): Select
    {
        return $this->innerJoin($table, $firstTableField, $operator, $secondTableField);
    }

    /**
     * Add one field that will be fetched
     *
     * @param string $field
     * @param string $as
     *
     * @return Select
     */
    public function field(string $field, string $as = null): Select
    {
        $this->fields[] = [
          'name' => $field,
          'as' => $as
        ];

        return $this;
    }

    /**
     * Add fields to fetch by passing array of fields
     *
     * @param array $fields
     * @param null|string $alias
     *
     * @return Select
     */
    public function fields(array $fields, string $alias = null): Select
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
     * @return Select
     */
    public function resetFields(): Select
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
     * @return Select
     */
    public function groupBy(string $field): Select
    {
        $this->groupBy[] = [
          'field' => $field
        ];
        return $this;
    }

    /**
     * Reset GROUP BY (remove GROUP BY)
     * @return Select
     */
    public function resetGroupBy(): Select
    {
        $this->groupBy = [];
        return $this;
    }

    /**
     * Add HAVING to your SELECT query
     *
     * @param string $field
     * @param string $operator
     * @param mixed $value
     *
     * @return Select
     */
    public function having(string $field, string $operator = null, $value = null): Select
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
     * @param string $field
     * @param string $operator
     * @param mixed $value
     *
     * @return Select
     */
    public function orHaving(string $field, string $operator = null, $value = null): Select
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
     * @return Select
     */
    public function resetHaving(): Select
    {
        $this->having = [];
        return $this;
    }

    /**
     * Add field to ORDER BY
     *
     * @param string $field
     * @param string $direction
     *
     * @throws Exception
     * @return Select
     */
    public function orderBy(string $field, string $direction = null): Select
    {
        if ($direction === null) {
            $direction = 'ASC';
        } else {
            $direction = strtoupper($direction);
        }

        if ($direction !== 'ASC' && $direction !== 'DESC') {
            throw new Exception("Can not use invalid direction order ({$direction}) in ORDER BY statement");
        }

        $this->orderBy[] = [
          'field' => $field,
          'direction' => $direction
        ];
        return $this;
    }

    /**
     * Reset ORDER BY (remove ORDER BY)
     * @return Select
     */
    public function resetOrderBy(): Select
    {
        $this->orderBy = [];
        return $this;
    }

    /**
     * Set the LIMIT on query results
     *
     * @param int $start
     * @param int $howMuch
     *
     * @return Select
     */
    public function limit(int $start, int $howMuch): Select
    {
        $this->limit = new \stdClass;
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
     * @return Select
     */
    public function page(int $number, int $limitPerPage): Select
    {
        return $this->limit(($number - 1) * $limitPerPage, $limitPerPage);
    }

    /**
     * Reset LIMIT (remove the LIMIT)
     * @return Select
     */
    public function resetLimit(): Select
    {
        $this->limit = null;
        return $this;
    }

    /**
     * Get the query string prepared for PDO
     * @throws Exception
     * @return Query
     */
    public function getQuery(): Query
    {
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
            if ($from['table'] instanceof static) {
                /* @var $subSelect \Koldy\Db\Query\Select */
                $subSelect = $from['table'];
                $subSql = $subSelect->__toString();
                $subSql = str_replace("\n", "\n\t", $subSql);
                $query .= " (\n\t{$subSql}\n) {$from['alias']}\n";

                $subSelectBindings = $subSelect->getBindings();
                if (count($subSelectBindings) > 0) {
                    $this->bindings += $subSelectBindings;
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
                        if (substr($query, -5) == ' AND ') {
                            $query = substr($query, 0, -5);
                        }

                        $query .= " {$joinArg[0]} {$joinArg[1]} {$joinArg[2]}  {$joinArg[3]} AND ";

                    } else {
                        throw new Exception('Unknown JOIN argument');

                    }
                }

                $query = substr($query, 0, -5);
            } else {
                if ($join['operator'] === null) {
                    throw new Exception('Operator can\'t be null');
                }

                if ($join['second'] === null) {
                    throw new Exception('Second parameter can\'t be null');
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
                    $query .= "{$nl}{$link}{$having['field']} {$having['operator']} :having{$index}";
                    //$bindings[':having' . $index] = $having['value'];
                    $this->bindField($index, $having['value'], 'h');
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
     */
    public function fetchAll(): array
    {
        if (!$this->wasExecuted()) {
            $this->exec();
        }

        return $this->getAdapter()->getStatement()->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch all records as array of objects
     *
     * @param string|null $class the name of class on which you want the instance of - class has to be able to accept array in constructor
     *
     * @return array
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
     * Fetch only first record as object or return null if there is no records
     * @return array|null
     */
    public function fetchFirst(): ?array
    {
        if (!$this->wasExecuted()) {
            $this->exec();
        }

        $this->resetLimit()->limit(0, 1);
        $results = $this->fetchAll();
        return isset($results[0]) ? $results[0] : null;
    }

    /**
     * Fetch only first record as object
     *
     * @param string $class
     *
     * @return null|object
     */
    public function fetchFirstObj(string $class = null)
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

        $this->bindings = [];
        $this->resetLastQuery();
    }

}
