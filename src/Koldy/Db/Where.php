<?php declare(strict_types=1);

namespace Koldy\Db;

use Koldy\Db\Exception as DbException;
use Koldy\Db\Query\Bindings;

/**
 * Class Where handles SQL "where" statements using object oriented approach, so instead of manipulating with string,
 * methods are called. All values given here are automatically passed to PDOStatement as binding, so there's no chance
 * of SQL injection.
 *
 * @package Koldy\Db
 * @link https://koldy.net/framework/docs/2.0/database/where.md
 */
class Where
{

    /**
     * The array of where statements
     * @var array
     */
    protected array $where = [];

    protected Bindings | null $bindings = null;

    /**
     * Bind some value for PDO
     *
     * @param string $field
     * @param $value
     * @param string $prefix - optional, use in special cases when there might a field duplicate, usually in clones or sub queries
     *
     * @return string - the bind name
     */
    public function bind(string $field, $value, string $prefix = ''): string
    {
    	if ($this->bindings === null) {
    		$this->bindings = new Bindings();
	    }

    	$parameter = $prefix . $field;
        return $this->bindings->makeAndSet($parameter, $value);
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
    private function addCondition(string $link, mixed $field, mixed $value, string $operator): static
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
    public function where(mixed $field, mixed $valueOrOperator = null, mixed $value = null): static
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
    public function orWhere(mixed $field, mixed $valueOrOperator = null, mixed $value = null): static
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
    public function whereNull(string $field): static
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
    public function orWhereNull(string $field): static
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
    public function whereNotNull(string $field): static
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
    public function orWhereNotNull(string $field): static
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
    public function whereBetween(string $field, mixed $value1, mixed $value2): static
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
    public function orWhereBetween(string $field, mixed $value1, mixed $value2): static
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
    public function whereNotBetween(string $field, mixed $value1, mixed $value2): static
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
    public function orWhereNotBetween(string $field, mixed $value1, mixed $value2): static
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
    public function whereIn(string $field, array $values): static
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
    public function orWhereIn(string $field, array $values): static
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
    public function whereNotIn(string $field, array $values): static
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
    public function orWhereNotIn(string $field, array $values): static
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
    public function whereLike(string $field, string $value): static
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
    public function orWhereLike(string $field, string $value): static
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
    public function whereNotLike(string $field, string $value): static
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
    public function orWhereNotLike(string $field, string $value): static
    {
        return $this->addCondition('OR', $field, $value, 'NOT LIKE');
    }

    /**
     * Is there a WHERE statement?
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
     * @return Bindings
     */
    public function getBindings(): Bindings
    {
    	if ($this->bindings === null) {
    		$this->bindings = new Bindings();
	    }

        return $this->bindings;
    }

    /**
     * Reset currently set bindings
     */
    public function resetBindings(): void
    {
        $this->bindings = new Bindings();
    }

	/**
	 * Get where statement appended to query
	 *
	 * @param array|null $whereArray
	 * @param int $cnt
	 *
	 * @return string
	 * @throws Exception
	 */
    public function getWhereSql(array $whereArray = null, int $cnt = 0): string
    {
    	if ($this->bindings === null) {
    		$this->bindings = new Bindings();
	    }

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

                /*
                foreach ($q->getBindings() as $k => $v) {
                    $this->bindings[$k] = $v;
                }
                */

                $this->bindings->addBindingsFromInstance($q->getBindings());

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
                            //$key = $this->bind($field, $value[0]);
	                        $key = $this->bindings->makeAndSet($field, $value[0]);
                            $query .= ":{$key}";
                        }

                        $query .= ' AND ';

                        if ($value[1] instanceof Expr) {
                            $query .= $value[1];
                        } else {
                            //$key = $this->bind($field, $value[1]);
	                        $key = $this->bindings->makeAndSet($field, $value[1]);
                            $query .= ":{$key}";
                        }

                        $query .= ")\n";
                        break;

                    case 'IN':
                    case 'NOT IN':
                        $query .= " ({$field} {$where['operator']} (";

                        foreach ($value as $val) {
                            //$key = $this->bind($field, $val);
	                        $key = $this->bindings->makeAndSet($field, $val);
                            $query .= ":{$key},";
                        }

                        $query = substr($query, 0, -1);
                        $query .= "))\n";
                        break;

                    // default: nothing by default
                }

            } else {
                //$key = $this->bind($field, $where['value']);
	            $key = $this->bindings->makeAndSet($field, $where['value']);
                $query .= " ({$field} {$where['operator']} :{$key})\n";

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
