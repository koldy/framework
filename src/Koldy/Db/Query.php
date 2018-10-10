<?php declare(strict_types=1);

namespace Koldy\Db;

use Generator;
use Koldy\Db;
use Koldy\Db\Adapter\AbstractAdapter;
use Koldy\Db\Query\Bindings;
use Koldy\Db\Query\Exception as QueryException;
use Koldy\Log;
use PDO;
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
     * @var Bindings|null
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
     * @param Bindings|array|null $bindings
     * @param string|null $adapter
     */
    public function __construct($query = null, $bindings = null, string $adapter = null)
    {
        $this->query = $query;
        $this->adapter = $adapter;

        if ($bindings === null) {
        	$this->bindings = new Bindings();

        } else if (is_array($bindings)) {
	        $this->bindings = new Bindings();
	        $this->bindings->setFromArray($bindings);

        } else if ($bindings instanceof Bindings) {
        	$this->bindings = $bindings;

        } else {
        	throw new \InvalidArgumentException('Invalid second argument provided, expected null, array or instance of Bindings');
        }
    }

    /**
     * Set query that will be executed
     *
     * @param string $query
     * @param Bindings|array|null $bindings
     *
     * @return Query
     */
    public function setQuery(string $query, $bindings = null): Query
    {
        $this->query = $query;

	    if ($bindings === null) {
		    $this->bindings = new Bindings();

	    } else if (is_array($bindings)) {
		    $this->bindings = new Bindings();
		    $this->bindings->setFromArray($bindings);

	    } else if ($bindings instanceof Bindings) {
		    $this->bindings = $bindings;

	    } else {
		    throw new \InvalidArgumentException('Invalid second argument provided, expected null, array or instance of Bindings');
	    }

        return $this;
    }

    /**
     * Get the SQL query, without bindings
     *
     * @return string
     * @throws Exception
     */
    public function getSQL(): string
    {
        if ($this->query === null) {
            throw new Exception('Query wasn\'t set');
        }

        if (is_object($this->query)) {
            if (method_exists($this->query, '__toString')) {
                return $this->query->__toString();
            }
        } else {
            return trim($this->query);
        }

        $className = get_class($this->query);
        throw new Exception("Can not use class={$className} as database query because it can't be used as string");
    }

    /**
     * @return AbstractAdapter
     * @throws Exception
     * @throws \Koldy\Config\Exception
     * @throws \Koldy\Exception
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
	 * @throws Adapter\Exception
	 * @throws Exception
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Exception
	 */
    public function getStatement(): PDOStatement
    {
        return $this->getAdapter()->getStatement();
    }

    /**
     * @param Bindings|array $bindings
     *
     * @return Query
     */
    public function setBindings($bindings): Query
    {
	    if (is_array($bindings)) {
		    $this->bindings = new Bindings();
		    $this->bindings->setFromArray($bindings);

	    } else if ($bindings instanceof Bindings) {
		    $this->bindings = $bindings;

	    } else {
		    throw new \InvalidArgumentException('Invalid second argument provided, expected null, array or instance of Bindings');
	    }

        return $this;
    }

    /**
     * @return Bindings
     */
    public function getBindings(): Bindings
    {
        return $this->bindings;
    }

    /**
     * Execute query
     *
     * @throws QueryException
     * @throws \Koldy\Exception
     */
    public function exec(): void
    {
        $this->queryExecuted = false;
        $sql = $this->getSQL();
        $adapter = $this->getAdapter();

        $adapter->prepare($sql);
        $bindings = $this->getBindings();

        try {
            foreach ($bindings->getBindings() as $bind) {
	            $adapter->getStatement()->bindValue($bind->getParameter(), $bind->getValue(), $bind->getType());
            }

	        $adapter->getStatement()->execute();

            Log::sql("{$this->adapter}>>>\n{$this->debug()}");
        } catch (PDOException $e) {
            // more descriptive error handling when query fails

            switch ($e->getCode()) {
                case 'HY093': // PDO miss match parameter count
                    //Log::debug("[{$e->getCode()}] Tried and failed to execute SQL query:\n{$sql}");
                    //Log::debug('BINDINGS', $bindings->getAsArray());
	                $exception = new QueryException("[{$e->getCode()}] Query bindings parameter miss match: {$e->getMessage()}", (int) $e->getCode(), $e);
                    break;

                default:
                    //Log::debug("[{$e->getCode()}] Tried and failed to execute SQL query:\n{$this->debug()}");
	                $exception = new QueryException("[{$e->getCode()}] Tried and failed to execute SQL query: {$e->getMessage()}", (int) $e->getCode(), $e);
                    break;
            }

            $exception->setAdapter($adapter->getConfigKey());
	        $exception->setSql($this->debug());
	        $exception->setBindings($bindings);
	        $exception->setWasPrepared(true);
            throw $exception;
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
     * @throws QueryException
     * @throws \Koldy\Exception
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
     * @throws QueryException
     * @throws \Koldy\Exception
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
     * Fetch all results from this query and get array of stdClass instances or your custom class instances
     *
     * @param string $class
     *
     * @return Generator
     * @throws QueryException
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
    }

    /**
     * Get next key index
     *
     * @return int
     *
     * @deprecated
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
     *
     * @deprecated
     */
    public static function getBindFieldName(string $field): string
    {
        $field = str_replace('.', '_', $field);
        $field = str_replace('(', '', $field);
        $field = str_replace(')', '', $field);
	    $field = str_replace('*', '', $field);

	    return strtolower($field) . static::getKeyIndex();
    }

    /**
     * Return some debug information about the query you built
     *
     * @param bool $oneLine return query in one line
     *
     * @return string
     *
     * @throws QueryException
     * @throws \Koldy\Exception
     */
    public function debug(bool $oneLine = false): string
    {
        $query = $this->getSQL();

        foreach ($this->getBindings()->getBindings() as $parameter => $bind) {
            if ($parameter[0] == ':') {
                $parameter = substr($parameter, 1);
            }

            $value = $bind->getValue();

            $type = gettype($value);
            switch ($type) {
                case 'string':
                    $query = str_replace(":{$parameter}", ("'" . addslashes((string) $value) . "'"), $query);
                    break;

                case 'integer':
                case 'float':
                case 'double':
                    $query = str_replace(":{$parameter}", $value, $query);
                    break;

                case 'NULL':
                    $query = str_replace(":{$parameter}", 'NULL', $query);
                    break;

                case 'boolean':
                    $true = (bool)$value;
                    $query = str_replace(":{$parameter}", $true ? 'true' : 'false', $query);
                    break;

                case 'object':
                case 'array':
                case 'resource':
                case 'unknown type':
                    throw new QueryException("Unsupported type ({$type}) was passed as parameter ({$parameter}) to SQL query");
                    break;

                default:
                    throw new \Koldy\Exception('Unknown type: ' . $type);
                    break;
            }
        }

        if ($oneLine) {
            $query = str_replace("\t", '', $query);
            $query = str_replace("\n", ' ', $query);
            $query = str_replace('  ', ' ', $query);
        }

        return $query;
    }

    /**
     * @return string
     * @throws \Koldy\Exception
     */
    public function __toString()
    {
        try {
            return $this->debug(true);
        } catch (Exception $e) {
            return 'Query [NOT SET]';
        }
    }

}
