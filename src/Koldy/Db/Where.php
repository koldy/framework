<?php declare(strict_types=1);

namespace Koldy\Db;

use Koldy\Db\Exception as DbException;

class Where
{

    /**
     * The array of where statements
     * @var array
     */
    protected $where = [];

    /**
     * @var array
     */
    protected $bindings = [];

    /**
     * Bind some value for PDO
     *
     * @param string $field
     * @param $value
     * @param string $prefix - optional, use in special cases when there might a field duplicate, usually in clones or sub queries
     *
     * @return string
     */
    public function bind(string $field, $value, string $prefix = ''): string
    {
        $bindFieldName = $prefix . Query::getBindFieldName($field);
        $this->bindings[$bindFieldName] = $value;
        return $bindFieldName;
    }

    /**
     * Add condition to where statements
     *
     * @param string $link
     * @param mixed $field
     * @param mixed $value
     * @param string $operator
     *
     * @return $this
     */
    private function addCondition(string $link, $field, $value, string $operator)
    {
        $this->where[] = [
          'link' => $link,
          'field' => $field,
          'operator' => $operator,
          'value' => $value
        ];

        return $this;
    }

    /**
     * Add AND where statement
     *
     * @param mixed $field
     * @param mixed $valueOrOperator
     * @param mixed $value
     *
     * @return $this
     *
     * @example where('id', 2) produces WHERE id = 2
     * @example where('id', '00385') produces WHERE id = '00385'
     * @example where('id', '>', 5) produces WHERE id > 5
     * @example where('id', '<=', '0100') produces WHERE id <= '0100'
     */
    public function where($field, $valueOrOperator = null, $value = null)
    {
        if (is_string($field) && $valueOrOperator === null) {
            throw new \InvalidArgumentException('Invalid second argument; argument must not be null in case when first argument is string');
        }

        return $this->addCondition('AND', $field, ($value === null) ? $valueOrOperator : $value, ($value === null) ? '=' : $valueOrOperator);
    }

    /**
     * Add OR where statement
     *
     * @param mixed $field
     * @param mixed $valueOrOperator
     * @param mixed $value
     *
     * @return $this
     */
    public function orWhere($field, $valueOrOperator = null, $value = null)
    {
        if (is_string($field) && $valueOrOperator === null) {
            throw new \InvalidArgumentException('Invalid second argument; argument must not be null');
        }

        return $this->addCondition('OR', $field, ($value === null) ? $valueOrOperator : $value, ($value === null) ? '=' : $valueOrOperator);
    }

    /**
     * Add WHERE field IS NULL
     *
     * @param string $field
     *
     * @return $this
     */
    public function whereNull(string $field)
    {
        return $this->addCondition('AND', $field, new Expr('NULL'), 'IS');
    }

    /**
     * Add OR field IS NULL
     *
     * @param string $field
     *
     * @return $this
     */
    public function orWhereNull(string $field)
    {
        return $this->addCondition('OR', $field, new Expr('NULL'), 'IS');
    }

    /**
     * Add WHERE field IS NOT NULL
     *
     * @param string $field
     *
     * @return $this
     */
    public function whereNotNull(string $field)
    {
        return $this->addCondition('AND', $field, new Expr('NULL'), 'IS NOT');
    }

    /**
     * Add OR field IS NOT NULL
     *
     * @param string $field
     *
     * @return $this
     */
    public function orWhereNotNull(string $field)
    {
        return $this->addCondition('OR', $field, new Expr('NULL'), 'IS NOT');
    }

    /**
     * Add WHERE field is BETWEEN two values
     *
     * @param string $field
     * @param mixed $value1
     * @param mixed $value2
     *
     * @return $this
     */
    public function whereBetween(string $field, $value1, $value2)
    {
        return $this->addCondition('AND', $field, [$value1, $value2], 'BETWEEN');
    }

    /**
     * Add OR field is BETWEEN two values
     *
     * @param string $field
     * @param mixed $value1
     * @param mixed $value2
     *
     * @return $this
     */
    public function orWhereBetween($field, $value1, $value2)
    {
        return $this->addCondition('OR', $field, [$value1, $value2], 'BETWEEN');
    }

    /**
     * Add WHERE field is NOT BETWEEN two values
     *
     * @param string $field
     * @param mixed $value1
     * @param mixed $value2
     *
     * @return $this
     */
    public function whereNotBetween($field, $value1, $value2)
    {
        return $this->addCondition('AND', $field, [$value1, $value2], 'NOT BETWEEN');
    }

    /**
     * Add OR field is NOT BETWEEN two values
     *
     * @param string $field
     * @param mixed $value1
     * @param mixed $value2
     *
     * @return $this
     */
    public function orWhereNotBetween($field, $value1, $value2)
    {
        return $this->addCondition('OR', $field, [$value1, $value2], 'NOT BETWEEN');
    }

    /**
     * Add WHERE field is IN array of values
     *
     * @param string $field
     * @param array $values
     *
     * @return $this
     */
    public function whereIn(string $field, array $values)
    {
        return $this->addCondition('AND', $field, array_values($values), 'IN');
    }

    /**
     * Add OR field is IN array of values
     *
     * @param string $field
     * @param array $values
     *
     * @return $this
     */
    public function orWhereIn(string $field, array $values)
    {
        return $this->addCondition('OR', $field, array_values($values), 'IN');
    }

    /**
     * Add WHERE field is NOT IN array of values
     *
     * @param string $field
     * @param array $values
     *
     * @return $this
     */
    public function whereNotIn(string $field, array $values)
    {
        return $this->addCondition('AND', $field, array_values($values), 'NOT IN');
    }

    /**
     * Add OR field is NOT IN array of values
     *
     * @param string $field
     * @param array $values
     *
     * @return $this
     */
    public function orWhereNotIn(string $field, array $values)
    {
        return $this->addCondition('OR', $field, array_values($values), 'NOT IN');
    }

    /**
     * Add WHERE field is LIKE
     *
     * @param string $field
     * @param string $value
     *
     * @return $this
     */
    public function whereLike(string $field, $value)
    {
        return $this->addCondition('AND', $field, $value, 'LIKE');
    }

    /**
     * Add OR field is LIKE
     *
     * @param string $field
     * @param string $value
     *
     * @return $this
     */
    public function orWhereLike($field, $value)
    {
        return $this->addCondition('OR', $field, $value, 'LIKE');
    }

    /**
     * Add WHERE field is NOT LIKE
     *
     * @param string $field
     * @param string $value
     *
     * @return $this
     */
    public function whereNotLike($field, $value)
    {
        return $this->addCondition('AND', $field, $value, 'NOT LIKE');
    }

    /**
     * Add OR field is NOT LIKE
     *
     * @param string $field
     * @param string $value
     *
     * @return $this
     */
    public function orWhereNotLike($field, $value)
    {
        return $this->addCondition('OR', $field, $value, 'NOT LIKE');
    }

    /**
     * Is there any WHERE statement
     *
     * @return boolean
     */
    protected function hasWhere(): bool
    {
        return count($this->where) > 0;
    }

    /**
     * Is this where statement empty or not
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return !$this->hasWhere();
    }

    /**
     * @return array
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Reset currently set bindings
     */
    public function resetBindings(): void
    {
        $this->bindings = [];
    }

    /**
     * Get where statement appended to query
     *
     * @param array $whereArray
     * @param int $cnt
     *
     * @throws Exception
     * @return string
     */
    protected function getWhereSql(array $whereArray = null, $cnt = 0): string
    {
        $query = '';

        if ($whereArray === null) {
            $whereArray = $this->where;
        }

        foreach ($whereArray as $index => $where) {
            if ($index > 0) {
                $query .= "\t{$where['link']}";
            }

            $field = $where['field'];
            $value = $where['value'];

            if (gettype($field) == 'object' && $value === null) {
                // function or instance of self is passed, do something

                $q = ($field instanceof self) ? clone $field : $field(new self());
                if ($q === null) {
                    throw new DbException('Can not build query, statement\'s where function didn\'t return anything');
                }

                $whereSql = trim($q->getWhereSql(null, $cnt++));
                $whereSql = str_replace("\n", ' ', $whereSql);
                $whereSql = str_replace("\t", '', $whereSql);

                $query .= " ({$whereSql})\n";
                foreach ($q->getBindings() as $k => $v) {
                    $this->bindings[$k] = $v;
                }

            } else if ($value instanceof Expr) {
                $query .= " ({$field} {$where['operator']} {$value})\n";

            } else if (is_array($value)) {

                switch ($where['operator']) {
                    case 'BETWEEN':
                    case 'NOT BETWEEN':
                        $query .= " ({$field} {$where['operator']} ";

                        if ($value[0] instanceof Expr) {
                            $query .= $value[0];
                        } else {
                            $key = $this->bind($field, $value[0]);
                            $query .= ":{$key}";
                            //$this->bindings[':' . $key] = $value[0];
                        }

                        $query .= ' AND ';

                        if ($value[1] instanceof Expr) {
                            $query .= $value[1];
                        } else {
                            //$key = Query::getBindFieldName($field);
                            $key = $this->bind($field, $value[1]);
                            $query .= ":{$key}";
                            //$this->bindings[':' . $key] = $value[1];
                        }

                        $query .= ")\n";
                        break;

                    case 'IN':
                    case 'NOT IN':
                        $query .= " ({$field} {$where['operator']} (";

                        foreach ($value as $val) {
                            //$key = Query::getBindFieldName($field);
                            $key = $this->bind($field, $val);
                            $query .= ":{$key},";
                            //$this->bindings[':' . $key] = $val;
                        }

                        $query = substr($query, 0, -1);
                        $query .= "))\n";
                        break;

                    // default: nothing by default
                }

            } else {
                //$key = Query::getBindFieldName($field);
                $key = $this->bind($field, $where['value']);
                $query .= " ({$field} {$where['operator']} :{$key})\n";
                //$this->bindings[':' . $key] = $where['value'];

            }

        }

        return $query;
    }

    /**
     * @return Where
     */
    public static function init(): Where
    {
        return new self();
    }

}
