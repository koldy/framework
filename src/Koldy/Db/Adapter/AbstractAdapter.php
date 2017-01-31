<?php declare(strict_types = 1);

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
     * @throws QueryException
     */
    public function getStatement(): PDOStatement
    {
        if ($this->stmt === null) {
            throw new QueryException('Can not get statement when it\'s not set; did you prepare your query before getting the PDO statement?');
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
            Log::error("Can't prepare query statement: {$queryStatement}");
            throw new QueryException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Begin SQL transaction
     *
     * @throws Exception
     */
    public function beginTransaction(): void
    {
        if ($this->getPDO()->beginTransaction() === false) {
            throw new Exception("Can not start PDO transaction for adapter={$this->getConfigKey()}");
        }
    }

    /**
     * Commit SQL transaction
     *
     * @throws Exception
     */
    public function commit(): void
    {
        if ($this->getPDO()->commit() === false) {
            throw new Exception("Can not commit PDO transaction for adapter={$this->getConfigKey()}");
        }
    }

    /**
     * Rollback previously started SQL transaction
     *
     * @throws Exception
     */
    public function rollBack(): void
    {
        if ($this->getPDO()->rollBack() === false) {
            throw new Exception("Can not commit PDO transaction for adapter={$this->getConfigKey()}");
        }
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
     *
     * @return Query\Select
     */
    public function select(string $table = null): Query\Select
    {
        return new Query\Select($table, $this->getConfigKey());
    }

    /**
     * @param string|null $table
     * @param array|null $rowValues
     *
     * @return Query\Insert
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

}