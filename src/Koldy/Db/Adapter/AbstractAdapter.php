<?php declare(strict_types=1);

namespace Koldy\Db\Adapter;

use Koldy\Db\Query;
use Koldy\Db\Query\Exception as QueryException;
use Koldy\Log;
use PDO;
use PDOException;
use PDOStatement;

abstract class AbstractAdapter
{

    /**
     * @var array
     */
    protected $config;

    /**
     * @var string|null
     */
    private $configKey = null;

    /**
     * @var \PDO
     */
    protected $pdo = null;

    /**
     * @var PDOStatement
     */
    protected $stmt = null;

    /**
     * @var Query|null
     */
    private $lastQuery = null;

	/**
	 * Number of "active" database transactions; allows "nesting" multiple db transactions
	 *
	 * @var int
	 */
	private $transactions = 0;

    /**
     * AbstractAdapter constructor.
     *
     * @param array $config
     * @param string|null $configKey
     */
    public function __construct(array $config = [], string $configKey = null)
    {
        $this->config = $config;
        $this->configKey = $configKey;
        $this->checkConfig($config);
    }

    /**
     * Check if configuration has all required keys
     *
     * @param array $config
     */
    abstract protected function checkConfig(array $config): void;

    /**
     * Get connection type (or better say, database type)
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->config['type'];
    }

    /**
     * @return PDO
     */
    public function getPDO(): PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }

        return $this->pdo;
    }

    /**
     * Get the config array used for this adapter
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get the config key name of this adapter. This must not be null!
     *
     * @return string
     */
    public function getConfigKey(): string
    {
        return $this->configKey;
    }

    /**
     * @param string $keyIdentifier
     */
    public function setConfigKey(string $keyIdentifier): void
    {
        $this->configKey = $keyIdentifier;
    }

    /**
     * Connect to database
     */
    abstract public function connect(): void;

    /**
     * Reconnect to database
     *
     * @throws QueryException
     * @throws \Koldy\Exception
     */
    public function reconnect(): void
    {
        $this->close();
        $this->query('SELECT 1')->exec();
    }

    /**
     * Close connection to database
     */
    abstract public function close(): void;

	/**
	 * @return PDOStatement
	 * @throws Exception
	 */
    public function getStatement(): PDOStatement
    {
        if ($this->stmt === null) {
            throw new Exception('Can not get statement when it\'s not set; did you prepare your query before getting the PDO statement?');
        }

        return $this->stmt;
    }

    /**
     * @param string $queryStatement
     *
     * @throws QueryException
     */
    public function prepare(string $queryStatement): void
    {
        try {
            $this->stmt = $this->getPDO()->prepare($queryStatement);
        } catch (PDOException $e) {
            //$dashes = str_repeat('=', 50);
            //Log::notice("Can't prepare query statement ({$this->getConfigKey()}):\n{$dashes}\n{$queryStatement}\n{$dashes}");

            $exception = new QueryException($e->getMessage(), (int)$e->getCode(), $e);
            $exception->setSql($queryStatement);
            throw $exception;
        }
    }

	/**
	 * Begin SQL transaction
	 *
	 * @throws Exception
	 */
	public function beginTransaction(): void
	{
		if ($this->transactions === 0) {
			if ($this->getPDO()->beginTransaction()) {
				Log::sql("{$this->configKey}>>>PDO beginTransaction");
			} else {
				throw new Exception("Can not start PDO transaction for adapter={$this->getConfigKey()}");
			}
		}

		$this->transactions++;
	}

	/**
	 * Commit SQL transaction
	 *
	 * @throws Exception
	 */
	public function commit(): void
	{
		if ($this->transactions === 1) {
			if ($this->getPDO()->commit()) {
				Log::sql("{$this->configKey}>>>PDO commit");
			} else {
				throw new Exception("Can not commit PDO transaction for adapter={$this->getConfigKey()}");
			}
		}

		$this->transactions--;
	}

	/**
	 * Rollback previously started SQL transaction
	 *
	 * @throws Exception
	 */
	public function rollBack(): void
	{
		if ($this->transactions === 1) {
			if ($this->getPDO()->rollBack()) {
				Log::sql("{$this->configKey}>>>PDO rollBack");
			} else {
				throw new Exception("Can not commit PDO transaction for adapter={$this->getConfigKey()}");
			}
		}

		$this->transactions--;
	}

    /**
     * Get new query
     *
     * @param string $query
     * @param array|null $bindings
     *
     * @return Query
     */
    public function query(string $query, array $bindings = null): Query
    {
        $this->lastQuery = new Query($query, $bindings, $this->configKey);
        return $this->lastQuery;
    }

	/**
	 * @param string|null $table
	 * @param string|null $tableAlias
	 *
	 * @return Query\Select
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Exception
	 */
    public function select(string $table = null, string $tableAlias = null): Query\Select
    {
        $select = new Query\Select($table, $tableAlias);
        $select->setAdapter($this->getConfigKey());
        return $select;
    }

	/**
	 * @param string|null $table
	 * @param array|null $rowValues
	 *
	 * @return Query\Insert
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Db\Exception
	 * @throws \Koldy\Exception
	 * @throws \Koldy\Json\Exception
	 */
    public function insert(string $table = null, array $rowValues = null): Query\Insert
    {
        return new Query\Insert($table, $rowValues, $this->getConfigKey());
    }

	/**
	 * @param string|null $table
	 * @param array|null $values
	 *
	 * @return Query\Update
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Exception
	 */
    public function update(string $table = null, array $values = null): Query\Update
    {
        return new Query\Update($table, $values, $this->getConfigKey());
    }

    /**
     * @param string|null $table
     *
     * @return Query\Delete
     */
    public function delete(string $table = null)
    {
        return new Query\Delete($table, $this->getConfigKey());
    }

	/**
	 * @param string|null $keyName
	 *
	 * @return string
	 * @throws Exception
	 */
	public function getLastInsertId(string $keyName = null)
	{
		try {
			$id = $this->getPDO()->lastInsertId($keyName);
		} catch (PDOException $e) {
			throw new Exception('Unable to get last insert ID' . ($keyName !== null ? " named \"{$keyName}\"" : ''));
		}

		return $id;
	}

}
