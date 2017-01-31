<?php declare(strict_types = 1);

namespace Koldy\Db;

use Koldy\Db;
use Koldy\Db\Adapter\AbstractAdapter;
use Koldy\Db\Query\Exception as QueryException;
use Koldy\Log;
use PDOException;
use PDOStatement;

class Query
{

    /**
     * @var string
     */
    protected $adapter = null;

    /**
     * @var null|object|string
     */
    protected $query = null;

    /**
     * @var array|null
     */
    protected $bindings = null;

    /**
     * @var mixed
     */
    protected $result = null;

    /**
     * @var bool
     */
    private $queryExecuted = false;

    /**
     * @var int
     */
    private static $keyIndex = 0;

    /**
     * Query constructor.
     *
     * @param string|object|null $query
     * @param array|null $bindings
     * @param string|null $adapter
     */
    public function __construct($query = null, array $bindings = null, string $adapter = null)
    {
        $this->query = $query;
        $this->bindings = $bindings;
        $this->adapter = $adapter;
    }

    /**
     * Set query that will be executed
     *
     * @param string $query
     * @param array|null $bindings
     *
     * @return Query
     */
    public function setQuery(string $query, array $bindings = []): Query
    {
        $this->query = $query;

        if (count($bindings) > 0) {
            $this->bindings = $bindings;
        }

        return $this;
    }

    /**
     * Get the SQL query, without bindings
     *
     * @return string
     * @throws QueryException
     */
    public function getSQL(): string
    {
        if ($this->query === null) {
            throw new QueryException('Query wasn\'t set');
        }

        if (is_object($this->query)) {
            if (method_exists($this->query, '__toString')) {
                return $this->query->__toString();
            }
        } else {
            return trim($this->query);
        }

        $className = get_class($this->query);
        throw new QueryException("Can not use class={$className} as database query because it can't be used as string");
    }

    /**
     * @return AbstractAdapter
     */
    public function getAdapter(): AbstractAdapter
    {
        return Db::getAdapter($this->adapter);
    }

    /**
     * Get the adapter key name from config that will be used on this adapter
     *
     * @return string
     */
    public function getAdapterConnection(): string
    {
        return $this->adapter;
    }

    /**
     * @param string $adapter
     *
     * @return Query
     */
    public function setAdapterConnection(string $adapter): Query
    {
        $this->adapter = $adapter;
        return $this;
    }

    /**
     * Get the last statement
     *
     * @return PDOStatement
     */
    public function getStatement(): PDOStatement
    {
        return $this->getAdapter()->getStatement();
    }

    /**
     * @param array $bindings
     *
     * @return Query
     */
    public function setBindings(array $bindings): Query
    {
        $this->bindings = $bindings;
        return $this;
    }

    /**
     * @return array
     */
    public function getBindings(): array
    {
        return $this->bindings ?? [];
    }

    /**
     * Execute query
     *
     * @throws QueryException
     */
    public function exec(): void
    {
        $this->queryExecuted = false;

        $this->getAdapter()->prepare($this->getSQL());

        try {
            $bindings = $this->getBindings();
            if (count($bindings) == 0) {
                $this->getAdapter()->getStatement()->execute();
            } else {
                $this->getAdapter()->getStatement()->execute($bindings);
            }

            Log::sql("{$this->adapter}>>>\n{$this->debug()}");
        } catch (PDOException $e) {
            Log::error("Tried and failed to execute SQL query:\n{$this->debug()}");
            throw new QueryException($e->getMessage(), $e->getCode(), $e);
        }

        $this->queryExecuted = true;
    }

    /**
     * @return bool
     */
    public function wasExecuted(): bool
    {
        return $this->queryExecuted;
    }

    /**
     * Fetch all results from this query, get array of arrays
     *
     * @return array
     */
    public function fetchAll(): array
    {
        if (!$this->wasExecuted()) {
            $this->exec();
        }

        return $this->getAdapter()->getStatement()->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Fetch all results from this query and get array of stdClass instances or your custom class instances
     *
     * @param string $class
     *
     * @return array
     */
    public function fetchAllObj(string $class = null): array
    {
        if (!$this->wasExecuted()) {
            $this->exec();
        }

        if ($class === null) {
            return $this->getAdapter()->getStatement()->fetchAll(\PDO::FETCH_OBJ);
        } else {
            $objectInstances = [];

            foreach ($this->fetchAll() as $r) {
                $objectInstances[] = new $class($r);
            }

            return $objectInstances;
        }
    }

    /**
     * Get next key index
     *
     * @return int
     */
    public static function getKeyIndex(): int
    {
        if (self::$keyIndex == PHP_INT_MAX) {
            self::$keyIndex = 0;
        } else {
            self::$keyIndex++;
        }

        return self::$keyIndex;
    }

    /**
     * @param string $field
     *
     * @return string
     */
    public static function getBindFieldName(string $field): string
    {
        $field = str_replace('.', '_', $field);
        $field = str_replace('(', '', $field);
        $field = str_replace(')', '', $field);

        return $field . static::getKeyIndex();
    }

    /**
     * Return some debug information about the query you built
     *
     * @param bool $oneLine return query in one line
     *
     * @return string
     */
    public function debug(bool $oneLine = false): string
    {
        $query = $this->getSQL();

        foreach ($this->getBindings() as $key => $value) {
            if ($key[0] == ':') {
                $key = substr($key, 1);
            }

            if (is_numeric($value) && substr((string)$value, 0, 1) != '0') {
                $value = (string)$value;

                if (strlen($value) > 10) {
                    $query = str_replace(":{$key}", "'{$value}'", $query);
                } else {
                    $query = str_replace(":{$key}", $value, $query);
                }

            } else {
                $query = str_replace(":{$key}", ("'" . addslashes($value) . "'"), $query);
            }
        }

        if ($oneLine) {
            $query = str_replace("\t", '', $query);
            $query = str_replace("\n", ' ', $query);
            $query = str_replace('  ', ' ', $query);
        }

        return $query;
    }

    public function __toString()
    {
        try {
            return $this->debug(true);
        } catch (QueryException $e) {
            return 'Query [not set]';
        }
    }

}