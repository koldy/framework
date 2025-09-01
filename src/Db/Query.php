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
use Stringable;

class Query implements Stringable
{

	private static int $keyIndex = 0;

	protected string|null $adapter;

	protected string|object|null $query = null;

	protected Bindings $bindings;

	protected mixed $result = null;

	private bool $queryExecuted = false;

	/**
	 * Query constructor.
	 *
	 * @param string|object|null $query
	 * @param Bindings|array|null $bindings
	 * @param string|null $adapter
	 */
	public function __construct(
		string|object|null $query = null,
		Bindings|array|null $bindings = null,
		string|null $adapter = null
	) {
		$this->query = $query;
		$this->adapter = $adapter;

		if ($bindings === null) {
			$this->bindings = new Bindings();

		} else if (is_array($bindings)) {
			$this->bindings = new Bindings();
			$this->bindings->setFromArray($bindings);

		} else {
			$this->bindings = $bindings;
		}
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
	 * Set query that will be executed
	 *
	 * @param string $query
	 * @param Bindings|array|null $bindings
	 *
	 * @return static
	 */
	public function setQuery(string $query, Bindings|array|null $bindings = null): static
	{
		$this->query = $query;

		if ($bindings === null) {
			$this->bindings = new Bindings();

		} else if (is_array($bindings)) {
			$this->bindings = new Bindings();
			$this->bindings->setFromArray($bindings);

		} else {
			$this->bindings = $bindings;
		}

		return $this;
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
	 * @return static
	 */
	public function setAdapterConnection(string $adapter): static
	{
		$this->adapter = $adapter;
		return $this;
	}

	/**
	 * Fetch all results from this query and get array of stdClass instances or your custom class instances
	 *
	 * @param string|null $class
	 *
	 * @return array
	 * @throws QueryException
	 * @throws \Koldy\Exception
	 */
	public function fetchAllObj(string|null $class = null): array
	{
		if (!$this->wasExecuted()) {
			$this->exec();
		}

		if ($class === null) {
			return $this->getAdapter()->getStatement()->fetchAll(PDO::FETCH_OBJ);
		} else {
			$objectInstances = [];

			foreach ($this->fetchAll() as $r) {
				$objectInstances[] = new $class($r);
			}

			return $objectInstances;
		}
	}

	/**
	 * @return bool
	 */
	public function wasExecuted(): bool
	{
		return $this->queryExecuted;
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
					$exception = new QueryException("[{$e->getCode()}] Query bindings parameter miss match: {$e->getMessage()}",
						(int)$e->getCode(), $e);
					break;

				default:
					//Log::debug("[{$e->getCode()}] Tried and failed to execute SQL query:\n{$this->debug()}");
					$exception = new QueryException("[{$e->getCode()}] Tried and failed to execute SQL query: {$e->getMessage()}",
						(int)$e->getCode(), $e);
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
	 * @return string
	 * @throws \Koldy\Exception
	 */
	public function __toString(): string
	{
		try {
			return $this->debug(true);
		} catch (Exception $e) {
			return 'Query [NOT SET]';
		}
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
					$query = str_replace(":{$parameter}", ("'" . addslashes((string)$value) . "'"), $query);
					break;

				case 'integer':
				case 'float':
				case 'double':
					$query = str_replace(":{$parameter}", (string)$value, $query);
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
				case 'resource (closed)':
				case 'unknown type':
					throw new QueryException("Unsupported type ({$type}) was passed as parameter ({$parameter}) to SQL statement");

				default:
					throw new \Koldy\Exception('Unknown data type (' . $type . ') was passed to SQL statement');
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
	 * @return Bindings
	 */
	public function getBindings(): Bindings
	{
		return $this->bindings;
	}

	/**
	 * @param Bindings|array $bindings
	 *
	 * @return static
	 */
	public function setBindings(Bindings|array $bindings): static
	{
		if (is_array($bindings)) {
			$this->bindings = new Bindings();
			$this->bindings->setFromArray($bindings);

		} else {
			$this->bindings = $bindings;
		}

		return $this;
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

		return $this->getAdapter()->getStatement()->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * Fetch all results from this query and get array of stdClass instances or your custom class instances
	 *
	 * @param string|null $class
	 *
	 * @return Generator
	 * @throws QueryException
	 * @throws \Koldy\Exception
	 */
	public function fetchAllObjGenerator(string|null $class = null): Generator
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
	 * Fetch all results from this query and get array of arrays using Generator
	 *
	 * @return Generator
	 * @throws QueryException
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
	}

}
