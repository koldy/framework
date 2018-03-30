<?php declare(strict_types=1);

namespace Koldy;

use Koldy\Db\Adapter\{
  AbstractAdapter, MySQL, PostgreSQL, Sqlite
};
use Koldy\Db\Exception as DbException;
use Koldy\Db\Expr;
use Koldy\Db\Query;

class Db
{

    /**
     * Initialized adapters
     *
     * @var AbstractAdapter[]
     */
    private static $adapters = [];

    /**
     * @var array
     */
    private static $types = [
      'mysql' => MySQL::class,
      'postgres' => PostgreSQL::class,
      'postgresql' => PostgreSQL::class,
      'sqlite' => Sqlite::class
    ];

    /**
     * Get the database config
     *
     * @return Config
     * @throws Exception
     */
    public static function getConfig(): Config
    {
        return Application::getConfig('database', true);
    }

    /**
     * @return string
     * @throws Config\Exception
     * @throws Exception
     */
    public static function getDefaultAdapterKey(): string
    {
        return static::getConfig()->getFirstKey();
    }

    /**
     * @param string|null $configKey
     *
     * @return AbstractAdapter
     * @throws Config\Exception
     * @throws DbException
     * @throws Exception
     */
    public static function getAdapter(string $configKey = null): AbstractAdapter
    {
        $key = $configKey ?? static::getDefaultAdapterKey();

        if (!isset(static::$adapters[$key])) {
            static::$adapters[$key] = static::resolve(static::getConfig()->get($key));
            static::$adapters[$key]->setConfigKey($key);
        }

        return static::$adapters[$key];
    }

    /**
     * @param array $config
     *
     * @return AbstractAdapter
     * @throws DbException
     */
    private static function resolve(array $config): AbstractAdapter
    {
        if (!isset($config['type'])) {
            throw new DbException('Can not resolve database config when there\'s no type key');
        }

        $type = strtolower($config['type']);
        if (array_key_exists($type, static::$types)) {
            $class = static::$types[$type];
            return new $class($config);
        }

        throw new DbException("Trying to use invalid database adapter type {$config['type']}");
    }

    /**
     * Register another database type
     *
     * @param string $type
     * @param string $class
     *
     * @throws DbException
     */
    public static function registerType(string $type, string $class): void
    {
        if (isset(static::$types[$type])) {
            throw new DbException("Can not register database type={$type} when it was already registered");
        }

        static::$types[$type] = $class;
    }

    /**
     * @param string $keyIdentifier
     * @param AbstractAdapter $adapter
     *
     * @throws DbException
     */
    public static function addAdapter(string $keyIdentifier, AbstractAdapter $adapter): void
    {
        if (isset(static::$adapters[$keyIdentifier])) {
            throw new DbException("Can not add database adapter={$keyIdentifier} when it already exists");
        }

        static::$adapters[$keyIdentifier] = $adapter;
    }

    /**
     * Is there already adapter with given name
     *
     * @param string $name
     *
     * @return bool
     */
    public static function hasAdapter(string $name): bool
    {
        return isset(static::$adapters[$name]) && static::$adapters[$name] instanceof AbstractAdapter;
    }

    /**
     * Delete registered adapter. This action will also remove connection if it was connected to database
     *
     * @param string $keyIdentifier
     */
    public static function removeAdapter(string $keyIdentifier): void
    {
        if (isset(static::$adapters[$keyIdentifier])) {
            static::$adapters[$keyIdentifier]->close();
            unset(static::$adapters[$keyIdentifier]);
        }
    }

    /**
     * Disconnect all and remove all adapters
     */
    public static function removeAdapters(): void
    {
        foreach (static::$adapters as $adapter) {
            $adapter->close();
        }

        static::$adapters = [];
    }

    /**
     * Begin transaction on default DB adapter
     *
     * @throws Config\Exception
     * @throws DbException
     * @throws Db\Adapter\Exception
     * @throws Exception
     */
    public static function beginTransaction(): void
    {
        static::getAdapter()->beginTransaction();
    }

    /**
     * Commit current transaction on default DB adapter
     *
     * @throws Config\Exception
     * @throws DbException
     * @throws Db\Adapter\Exception
     * @throws Exception
     */
    public static function commit(): void
    {
        static::getAdapter()->commit();
    }

    /**
     * Rollback current transaction on default DB adapter
     *
     * @throws Config\Exception
     * @throws DbException
     * @throws Db\Adapter\Exception
     * @throws Exception
     */
    public static function rollBack(): void
    {
        static::getAdapter()->rollBack();
    }

    /**
     * Get the query that will be executed on default adapter
     *
     * @param string|object $query
     * @param array $bindings
     *
     * @return Query
     * @throws Config\Exception
     * @throws DbException
     * @throws Exception
     */
    public static function query($query, array $bindings = []): Query
    {
        return static::getAdapter()->query($query, $bindings);
    }

    /**
     * Get the SELECT query instance on default adapter
     *
     * @param string|null $table
     * @param string|null $tableAlias
     *
     * @return Query\Select
     * @throws Config\Exception
     * @throws DbException
     * @throws Exception
     */
    public static function select(string $table = null, string $tableAlias = null): Query\Select
    {
        return static::getAdapter()->select($table, $tableAlias);
    }

    /**
     * Get the INSERT query instance on default adapter
     *
     * @param string|null $table
     * @param array|null $rowValues
     *
     * @return Query\Insert
     * @throws Config\Exception
     * @throws DbException
     * @throws Exception
     * @throws Json\Exception
     */
    public static function insert(string $table = null, array $rowValues = null): Query\Insert
    {
        return static::getAdapter()->insert($table, $rowValues);
    }

    /**
     * Get the UPDATE query instance on default adapter
     *
     * @param string|null $table
     * @param array|null $values
     *
     * @return Query\Update
     * @throws Config\Exception
     * @throws DbException
     * @throws Exception
     */
    public static function update(string $table = null, array $values = null): Query\Update
    {
        return static::getAdapter()->update($table, $values);
    }

    /**
     * Get the DELETE query instance on default adapter
     *
     * @param string|null $table
     *
     * @return Query\Delete
     * @throws Config\Exception
     * @throws DbException
     * @throws Exception
     */
    public static function delete(string $table = null)
    {
        return static::getAdapter()->delete($table);
    }

    /**
     * Create new database expression and return instance
     *
     * @param string $expression
     *
     * @return Expr
     */
    public static function expr(string $expression): Expr
    {
        return new Expr($expression);
    }

}
