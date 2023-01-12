<?php declare(strict_types=1);

namespace Koldy\Db;

use Koldy\Db;
use Koldy\Db\Adapter\AbstractAdapter;
use Koldy\Db\Adapter\Exception;
use Koldy\Db\Exception\NotFoundException;
use Koldy\Db\Query\{
  Select, Insert, Update, Delete, ResultSet
};
use Koldy\Json;
use Stringable;

/**
 * Model is abstract class that needs to be extended with your defined class.
 * When you extend it, framework will know with which table it needs to work; or
 * simply define the table name you need. Check out the docs in link for more examples.
 *
 * @link http://koldy.net/docs/database/models
 */
abstract class Model implements Stringable
{

    /**
     * The DB adapter key from config on which the queries will be executed. Null means default adapter (or the first from configs/database.php)
     *
     * @var string|null
     */
    protected static string | null $adapter = null;

    /**
     * If you don't define the table name, the framework will assume the table
     * name by the called class name. Class \Db\User\Roles that extends Model class will try to use db_user_roles table in DB
     *
     * @var string|null
     */
    protected static string | null $table = null;

    /**
     * While working with tables in database, framework will always assume that
     * you have the field named "id" as unique identifier. If you have
     * your primary key with different name, please define it in the child
     * class.
     *
     * @var string|array
     */
    protected static string | array $primaryKey = 'id';

    /**
     * Assume that this table has auto increment field and that field is primary field.
     * @var bool
     */
    protected static string | bool $autoIncrement = true;

    /**
     * The data holder in this object
     *
     * @var array|null
     */
    private array | null $data = null;

    /**
     * This is the array that holds information loaded from database. When
     * you call save() method, this data will be compared to the data set in
     * object and update method will set only fields that are changed. If there
     * is no change, update() method will return 0 without triggering query on
     * database.
     *
     * @var array|null
     */
    private array | null $originalData = null;

    /**
     * Construct the instance with or without starting data
     *
     * @param array|null $data
     */
    public function __construct(array $data = null)
    {
        if ($data !== null) {
            // let's detect if $data contains primary keys, if it does, then $setOriginalData should be true
            if (is_array(static::$primaryKey)) {
                $setOriginalData = true;

                foreach (static::$primaryKey as $pk) {
                    if (!array_key_exists($pk, $data)) {
                        $setOriginalData = false;
                    }
                }
            } else {
                $setOriginalData = array_key_exists(static::$primaryKey, $data);
            }

            if ($setOriginalData) {
                $this->originalData = $data;
            }

            $this->data = $data;
        }
    }

    /**
     * @param string $property
     *
     * @return mixed|null
     * @throws Exception
     */
    final public function __get(string $property): mixed
    {
        if ($this->data === null) {
            $class = get_class($this);
            throw new Exception("Can not use __get() on {$class} because data wasn't set yet");
        }

        return $this->data[$property] ?? null;
    }

    /**
     * @param string $property
     * @param mixed $value
     */
    final public function __set(string $property, mixed $value): void
    {
        if ($this->data === null) {
            $this->data = [];
        }

        $this->data[$property] = $value;
    }

    /**
     * Set the array of values
     *
     * @param array $values
     *
     * @return static
     */
    final public function setData(array $values): static
    {
        if (!is_array($this->data)) {
            $this->data = $values;
        } else {
            $this->data = array_merge($this->data, $values);
        }

        return $this;
    }

    /**
     * Gets all data that this object currently has
     * @return array
     * @throws Exception
     */
    final public function getData(): array
    {
        if ($this->data === null) {
            $class = get_class($this);
            throw new Exception("Can not use getData() on {$class} because data wasn't set or loaded");
        }

        return $this->data;
    }

    /**
     * Does this object has a field?
     *
     * @param string $field
     *
     * @return bool
     */
    final public function has(string $field): bool
    {
        return array_key_exists($field, $this->data);
    }

    /**
     * Used for internal use, such as after unserialize() or such
     *
     * @param array|null $values
     *
     * @return static
     */
    final protected function setOriginalData(?array $values): static
    {
        $this->originalData = $values;
        return $this;
    }

    /**
     * Get the internal original data
     *
     * @return array|null
     */
    final protected function getOriginalData(): ?array
    {
        return $this->originalData;
    }

    /**
     * Manually set the adapter
     *
     * @param string|null $adapter
     */
    public static function setAdapterConnection(?string $adapter): void
    {
        static::$adapter = $adapter;
    }

    /**
     * @return null|string
     */
    public static function getAdapterConnection(): ?string
    {
        return static::$adapter;
    }

	/**
	 * Get the adapter for this model
	 *
	 * @return AbstractAdapter
	 * @throws Exception
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Exception
	 */
    public static function getAdapter(): AbstractAdapter
    {
        return Db::getAdapter(static::getAdapterConnection());
    }

	/**
	 * Begin transaction using this model's DB adapter
	 *
	 * @throws Adapter\Exception
	 * @throws Exception
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Exception
	 */
	public static function beginTransaction(): void
	{
		static::getAdapter()->beginTransaction();
	}

	/**
	 * Commit current transaction using this model's DB adapter
	 *
	 * @throws Adapter\Exception
	 * @throws Exception
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Exception
	 */
	public static function commit(): void
	{
		static::getAdapter()->commit();
	}

	/**
	 * Rollback current transaction on this model's DB adapter
	 *
	 * @throws Adapter\Exception
	 * @throws Exception
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Exception
	 */
	public static function rollBack(): void
	{
		static::getAdapter()->rollBack();
	}

    /**
     * Get the table name for database for this model. If your model class is
     * User\Login\History, then the database table name will be user_login_history
     *
     * @return string
     */
    final public static function getTableName(): string
    {
        if (static::$table === null) {
            return str_replace('\\', '_', strtolower(get_called_class()));
        }

        return static::$table;
    }

    /**
     * Insert the record in database with given array of data
     *
     * @param array $data pass array of data for this model \Koldy\Db\Model
     *
     * @return static
     * @throws Exception
     * @throws Json\Exception
     * @throws Query\Exception
     * @throws \Koldy\Exception
     */
    public static function create(array $data): static
    {
        $insert = new Insert(static::getTableName(), $data, static::getAdapterConnection());
        $insert->exec();

        if (static::$autoIncrement) {
            // ID should be fetched if $data contains ID, so, let's check
            if (is_string(static::$primaryKey) && isset($data[static::$primaryKey])) {
                // there there, we already have it, let's do nothing
            } else {
                $data[static::$primaryKey] = static::getLastInsertId();
            }
        }

        return new static($data);
    }

	/**
	 * Reloads this model with the latest data from database. It's using primary key to fetch the data. If primary key
	 * contains multiple columns, then all columns must be present in the model in order to refresh the data successfully.
	 *
	 * @return static
	 * @throws Exception
	 * @throws Query\Exception
	 * @throws \Koldy\Exception
	 */
    public function reload(): static
    {
        $pk = static::$primaryKey;

        if (is_array($pk)) {

            if (count($pk) === 0) {
                $class = get_class($this);
                throw new Exception("Can not reload model of {$class}, primary key definition is incorrect, it can't be empty array");
            }

            $select = [];
            foreach ($pk as $column) {
                if (!$this->has($column)) {
                    $class = get_class($this);
                    throw new Exception("Can not reload model of {$class}, primary key column {$column} is not set in model");
                }

                $pkValue = $this->$column;
                $select[$column] = $pkValue;
            }

            $condition = $select;
            $row = static::select()->where($select)->fetchFirst();
        } else {
            if (!$this->has($pk)) {
                $class = get_class($this);
                throw new Exception("Can not reload model of {$class}, primary key {$pk} is not set in model");
            }

            $pkValue = $this->$pk;
            $condition = [$pk => $pkValue];

            $row = static::select()->where($pk, $pkValue)->fetchFirst();
        }

        if ($row === null) {
            $class = get_class($this);

            $conditions = [];
            foreach ($condition as $key => $value) {
                $conditions[] = "{$key}={$value}";
            }

            $conditions = implode(', ', $conditions);
            throw new Exception("Can not reload model of {$class}, there is no record in database under {$conditions}");
        }

        return $this->setData($row);
    }

	/**
	 * If you statically created new record in database to the table with auto
	 * incrementing field, then you can use this static method to get the
	 * generated primary key
	 *
	 * @param null|string $keyName
	 *
	 * @return string
	 * @throws Exception
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Exception
	 * @example
	 *
	 *    if (User::create(array('first_name' => 'John', 'last_name' => 'Doe'))) {
	 *      echo User::getLastInsertId();
	 *    }
	 */
    public static function getLastInsertId(string $keyName = null): string
    {
        if (static::$autoIncrement) {
            if (is_string(static::$autoIncrement)) {
                $keyName = static::$autoIncrement;
            } else if (is_string(static::$primaryKey)) {
                $keyName = static::getTableName() . '_' . static::$primaryKey . '_seq';
            }

            return static::getAdapter()->getLastInsertId($keyName);
        } else {
            throw new Exception('Can not get last insert ID when model ' . get_called_class() . ' doesn\'t have auto_increment field');
        }
    }

    /**
     * Update the table with given array of data. Be aware that if you don't
     * pass the second parameter, then the whole table will be updated (the
     * query will be executed without the WHERE statement).
     *
     * @param  array $data
     * @param  int|float|string|array|Where|null $where OPTIONAL if you pass single value, framework will
     * assume that you passed primary key value. If you pass assoc array,
     * then the framework will use those to create the WHERE statement.
     *
     * @return int number of affected rows
     * @throws Query\Exception
     * @throws \Koldy\Exception
     * @example
     *
     *    User::update(array('first_name' => 'new name'), 5) will execute:
     *    UPDATE user SET first_name = 'new name' WHERE id = 5
     *
     *    User::update(array('first_name' => 'new name'), array('disabled' => 0)) will execute:
     *    UPDATE user SET first_name = 'new name' WHERE disabled = 0
     */
    public static function update(array $data, int | float | string | array | Where | null $where = null): int
    {
        $update = new Update(static::getTableName(), $data, static::getAdapterConnection());

        if ($where !== null) {
            if ($where instanceof Where) {
                $update->where($where);
            } else if (is_array($where)) {
                foreach ($where as $field => $value) {
                    $update->where($field, $value);
                }
            } else if (!is_array(static::$primaryKey) && (is_numeric($where) || is_string($where))) {
                $update->where(static::$primaryKey, $where);
            }
        }

        $update->exec();
        return $update->rowCount();
    }

    /**
     * Save this initialized object into database.
     *
     * @return int|null how many rows is affected, null if database wasn't contacted (for cases when FW detects
     * there's no changes, so there's no point on making a query)
     *
     * @throws Exception
     * @throws Json\Exception
     * @throws Query\Exception
     * @throws \Koldy\Exception
     */
    public function save(): ?int
    {
        $data = $this->getData();
        $originalData = (array)$this->originalData;
        $toUpdate = [];

        if (count($originalData) == 0) {
            // there's nothing in original data, means we need to insert this
            $toUpdate = $data;
        } else {
            foreach ($data as $field => $value) {
                if (array_key_exists($field, $originalData)) {
                    $doUpdate = false;

                    if ($value !== $originalData[$field]) {
                        // might need to update
                        if (is_scalar($value) && is_scalar($originalData[$field])) {
                            if ((string)$value !== (string)$originalData[$field]) {
                                $doUpdate = true;
                            }
                        } else {
                            $doUpdate = true;
                        }
                    }

                    if ($doUpdate) {
                        $toUpdate[$field] = $value;
                    }
                } else {
                    $toUpdate[$field] = $value;
                }
            }
        }

        if (count($toUpdate) > 0) {
            // we know we have something to update now

            if (!is_array(static::$primaryKey)) {
                if (isset($originalData[static::$primaryKey])) {
                    // we have pk value, so lets update
                    $update = new Update(static::getTableName(), $toUpdate, static::getAdapterConnection());

                    if (!is_array(static::$primaryKey)) {
                        $update->where(static::$primaryKey, $data[static::$primaryKey]);
                    } else {
                        foreach (static::$primaryKey as $field) {
                            $update->where($field, $data[$field]);
                        }
                    }

                    $result = $update->exec()->rowCount();
                    $this->originalData = $this->data;

                    return $result;
                } else {
                    // we don't have pk value, so lets insert
                    $insert = new Insert(static::getTableName(), $toUpdate, static::getAdapterConnection());
                    $insert->exec();
                    $this->data[static::$primaryKey] = Db::getAdapter(static::getAdapterConnection())
                      ->getLastInsertId();
                    $this->originalData = $this->data;
                    return 0;
                }
            } else {
                $update = new Update(static::getTableName(), null, static::getAdapterConnection());

                foreach (static::$primaryKey as $field) {
                    if (!$this->has($field)) {
                        throw new Exception('Can not execute save() method when primary key fields are not present in ' . get_class($this));
                    }

                    $update->where($field, $this->$field);

                    if (array_key_exists($field, $toUpdate)) {
                        unset($toUpdate[$field]);
                    }
                }

                $update->setValues($toUpdate);
                $result = $update->exec()->rowCount();
                $this->originalData = $this->data;

                return $result;
            }

        }

        return -1;
    }

    /**
     * Increment one numeric field in table on the row identified by primary key.
     * You can use this only if your primary key is just one field.
     *
     * @param string $field
     * @param int|float|string|array|Where $where the primary key value of the record
     * @param int $howMuch default 1
     *
     * @return int number of affected rows
     * @throws Exception
     * @throws Query\Exception
     * @throws \Koldy\Exception
     */
    public static function increment(string $field, int | float | string | array | Where $where, int $howMuch = 1): int
    {
        $update = new Update(static::getTableName(), null, static::getAdapterConnection());
        $update->increment($field, $howMuch);

        if ($where instanceof Where) {
            $update->where($where);
        } else if (is_array($where)) {
            foreach ($where as $field => $value) {
                $update->where($field, $value);
            }
        } else if (!is_array(static::$primaryKey) && (is_numeric($where) || is_string($where))) {
            $update->where(static::$primaryKey, $where);
        } else {
            throw new Exception('Unhandled increment case in DB model');
        }

        return $update->exec()->rowCount();
    }

    /**
     * Delete one or more records from the table defined in this model. If you
     * pass array, then array must contain field names and values that will be
     * used in WHERE statement. If you pass primitive value, method will treat
     * that as passed value for primary key field.
     *
     * @param int|float|string|array|Where $where
     *
     * @return integer How many records is deleted
     * @throws Query\Exception
     * @throws \Koldy\Exception
     * @example User::delete(1);
     * @example User::delete(array('group_id' => 5, 'parent_id' => 10));
     * @example User::delete(array('parent_id' => 10, array('time', '>', '2013-08-01 00:00:00')))
     *
     * @link http://koldy.net/docs/database/models#delete
     */
    public static function delete(int | float | string | array | Where $where): int
    {
        $delete = new Delete(static::getTableName(), static::getAdapterConnection());

        if ($where instanceof Where) {
            $delete->where($where);
        } else if (is_array($where)) {
            foreach ($where as $field => $value) {
                $delete->where($field, $value);
            }
        } else if (!is_array(static::$primaryKey) && (is_numeric($where) || is_string($where))) {
            $delete->where(static::$primaryKey, $where);
        }

        return $delete->exec()->rowCount();
    }

    /**
     * The same as static::delete(), only this will work only on instances.
     * Instance data will be kept in memory until destroyed.
     *
     * @see \Koldy\Db\Model::delete()
     * @return int
     * @throws Exception
     * @throws Query\Exception
     * @throws \Koldy\Exception
     */
    public function destroy(): int
    {
        $pk = static::$primaryKey;

        if (is_array($pk)) {
            $where = [];
            $data = $this->getData();
            foreach ($pk as $field) {
                if (!isset($data[$field])) {
                    throw new Exception("Can not destroy row from database when object doesn't contain '{$field}' in loaded data");
                }
                $where[$field] = $data[$field];
            }

            return static::delete($where);
        } else if (!isset($this->data[$pk])) {
            throw new Exception('Can not destroy row from database when object doesn\'t contain primary key\'s value');
        } else {
            return static::delete($this->data[$pk]);
        }
    }

	/**
	 * Fetch one record from database. You can pass one or two parameters.
	 * If you pass only one parameter, framework will assume that you want to
	 * fetch the record from database according to primary key defined in
	 * model. Otherwise, you can fetch the record by any other field you have.
	 * If your criteria returns more than one records, only first record will
	 * be taken.
	 *
	 * @param  int|float|string|array|Where $field primaryKey value, single field or assoc array of arguments for query
	 * @param  int|float|string|null $value
	 * @param array|null $fields
	 *
	 * @return static|null null will be returned if record is not found
	 * @throws Exception
	 * @throws Query\Exception
	 * @throws \Koldy\Exception
	 * @link http://koldy.net/docs/database/models#fetchOne
	 */
    public static function fetchOne(int | float | string | array | Where $field, int | float | string | null $value = null, array $fields = null): ?static
    {
        $select = static::select();

        if ($fields !== null) {
            $select->fields($fields);
        }

        if ($value === null) {
            if (is_array($field)) {

                foreach ($field as $k => $v) {
                    $select->where($k, $v);
                }

            } else if (is_array(static::$primaryKey)) {
                throw new Exception('Can not build SELECT query when primary key is not single column');

            } else if ($field instanceof Where) {
                $select->where($field);

            } else {
                $select->where(static::$primaryKey, $field);

            }

        } else {
            $select->where($field, $value);

        }

        $record = $select->fetchFirst();
        return ($record === null) ? null : new static($record);
    }

	/**
	 * Fetch one record from database. You can pass one or two parameters.
	 * If you pass only one parameter, framework will assume that you want to
	 * fetch the record from database according to primary key defined in
	 * model. Otherwise, you can fetch the record by any other field you have.
	 * If your criteria returns more than one records, only first record will
	 * be taken.
	 *
	 * @param  int|float|string|array|Where $field primaryKey value, single field or assoc array of arguments for query
	 * @param  int|float|string|null $value
	 * @param array|null $fields
	 *
	 * @return static
	 * @throws Exception
	 * @throws NotFoundException
	 * @throws Query\Exception
	 * @throws \Koldy\Exception
	 * @link http://koldy.net/docs/database/models#fetchOne
	 */
    public static function fetchOneOrFail(int | float | string | array | Where $field, int | float | string | null $value = null, array $fields = null): static
    {
        $record = static::fetchOne($field, $value, $fields);

        if ($record === null) {
            throw new NotFoundException('Record not found');
        }

        return $record;
    }

	/**
	 * Fetch the array of initialized records from database
	 *
	 * @param int|float|string|array|Where $where the WHERE condition
	 * @param array|null $fields array of fields to select; by default, all fields will be fetched
	 * @param string|null $orderField
	 * @param string|null $orderDirection
	 * @param int|null $limit
	 *
	 * @param int|null $start
	 *
	 * @return static[]
	 *
	 * @throws Query\Exception
	 * @throws \Koldy\Exception
	 * @link http://koldy.net/docs/database/models#fetch
	 */
    public static function fetch(
		int | float | string | array | Where $where,
		array $fields = null,
		string $orderField = null,
		string $orderDirection = null,
		int $limit = null,
		int $start = null
    ): array {
        $select = static::select();

        if ($fields !== null) {
            $select->fields($fields);
        }

        if ($where instanceof Where) {
            $select->where(clone $where);
        } else if (is_array($where)) {
            foreach ($where as $field => $value) {
                $select->where($field, $value);
            }
        } else if (!is_array(static::$primaryKey) && (is_numeric($where) || is_string($where))) {
            $select->where(static::$primaryKey, $where);
        }

        if ($orderField !== null) {
            $select->orderBy($orderField, $orderDirection);
        }

        if ($limit !== null) {
            $select->limit($start ?? 0, $limit);
        }

        $data = [];
        foreach ($select->fetchAll() as $r) {
            $data[] = new static($r);
        }

        return $data;
    }

	/**
	 * Fetch the array of initialized records from database, where key in the returned array is something from the
	 * results
	 *
	 * @param string $key The name of the column which will be taken from results to be used as key in array
	 * @param int|float|string|array|Where $where the WHERE condition
	 * @param array|null $fields array of fields to select; by default, all fields will be fetched
	 * @param string|null $orderField
	 * @param string|null $orderDirection
	 * @param int|null $limit
	 *
	 * @param int|null $start
	 *
	 * @return array
	 * @throws Query\Exception
	 * @throws \Koldy\Exception
	 * @link http://koldy.net/docs/database/models#fetch
	 */
    public static function fetchWithKey(
        string $key,
	    int | float | string | array | Where $where,
        array $fields = null,
        string $orderField = null,
        string $orderDirection = null,
        int $limit = null,
        int $start = null
    ): array {
        $data = [];

        foreach (static::fetch($where, $fields, $orderField, $orderDirection, $limit, $start) as $record) {
            $data[$record->$key] = $record;
        }

        return $data;
    }

	/**
	 * Fetch all records from database
	 *
	 * @param string|null $orderField
	 * @param string|null $orderDirection
	 *
	 * @return static[]
	 * @throws Query\Exception
	 * @throws \Koldy\Exception
	 * @link http://www.php.net/manual/en/pdo.constants.php
	 */
    public static function all(string $orderField = null, string $orderDirection = null): array
    {
        $select = static::select();

        if ($orderField !== null) {
            $select->orderBy($orderField, $orderDirection);
        }

        $data = [];
        foreach ($select->fetchAll() as $r) {
            $data[] = new static($r);
        }

        return $data;
    }

	/**
	 * Fetch key value pairs from database table
	 *
	 * @param string $keyField
	 * @param string $valueField
	 * @param int|float|string|array|Where|null $where
	 * @param string|null $orderField
	 * @param string|null $orderDirection
	 * @param int|null $limit
	 *
	 * @param int|null $start
	 *
	 * @return array
	 * @throws Query\Exception
	 * @throws \Koldy\Exception
	 * @link http://koldy.net/docs/database/models#fetchKeyValue
	 */
    public static function fetchKeyValue(
		string $keyField,
		string $valueField,
		int | float | string | array | Where | null $where = null,
		string $orderField = null,
		string $orderDirection = null,
		int $limit = null,
		int $start = null
    ): array {
        $select = static::select()->field($keyField, 'key_field')->field($valueField, 'value_field');

        if ($where !== null) {
            if ($where instanceof Where) {
                $select->where($where);
            } else if (is_array($where)) {
                foreach ($where as $field => $value) {
                    $select->where($field, $value);
                }
            } else if (!is_array(static::$primaryKey) && (is_numeric($where) || is_string($where))) {
                $select->where(static::$primaryKey, $where);
            }
        }

        if ($orderField !== null) {
            $select->orderBy($orderField, $orderDirection);
        }

        if ($limit !== null) {
            $select->limit($start ?? 0, $limit);
        }

        $data = [];
        foreach ($select->fetchAll() as $r) {
            $data[$r['key_field']] = $r['value_field'];
        }

        return $data;
    }

	/**
	 * Fetch numeric array of values from one column in database
	 *
	 * @param string $field
	 * @param int|float|string|array|Where|null $where
	 * @param string|null $orderField
	 * @param string|null $orderDirection
	 * @param int|null $limit
	 * @param int|null $start
	 *
	 * @return array or empty array if not found
	 * @throws Query\Exception
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Exception
	 * @example User::fetchArrayOf('id', Where::init()->where('id', '>', 50), 'id', 'asc') would return array(51,52,53,54,55,...)
	 * @link http://koldy.net/docs/database/models#fetchArrayOf
	 */
    public static function fetchArrayOf(
		string $field,
		int | float | string | array | Where | null $where = null,
		string $orderField = null,
		string $orderDirection = null,
		int $limit = null,
		int $start = null
    ): array {
        $select = static::select()->field($field, 'key_field');

        if ($where !== null) {
            if ($where instanceof Where) {
                $select->where($where);
            } else if (is_array($where)) {
                foreach ($where as $field => $value) {
                    $select->where($field, $value);
                }
            } else if (!is_array(static::$primaryKey) && (is_numeric($where) || is_string($where))) {
                $select->where(static::$primaryKey, $where);
            }
        }

        if ($orderField !== null) {
            $select->orderBy($orderField, $orderDirection);
        }

        if ($limit !== null) {
            $select->limit($start ?? 0, $limit);
        }

        $data = [];
        foreach ($select->fetchAll() as $r) {
            $data[] = $r['key_field'];
        }

        return $data;
    }

	/**
	 * Fetch only one record and return value from given column
	 *
	 * @param string $field
	 * @param int|float|string|array|Where|null $where
	 * @param string|null $orderField
	 * @param string|null $orderDirection
	 *
	 * @return mixed|null
	 * @throws Query\Exception
	 * @throws \Koldy\Exception
	 */
    public static function fetchOneValue(string $field, int | float | string | array | Where | null $where = null, string $orderField = null, string $orderDirection = null): mixed
    {
        $select = static::select()->field($field, 'key_field')->limit(0, 1);

        if ($where !== null) {
            if ($where instanceof Where) {
                $select->where($where);
            } else if (is_array($where)) {
                foreach ($where as $field => $value) {
                    $select->where($field, $value);
                }
            } else if (!is_array(static::$primaryKey) && (is_numeric($where) || is_string($where))) {
                $select->where(static::$primaryKey, $where);
            }
        }

        if ($orderField !== null) {
            $select->orderBy($orderField, $orderDirection);
        }

        $records = $select->fetchAll();
        if (count($records) == 0) {
            return null;
        }

        return $records[0]['key_field'];
    }

	/**
	 * Fetch only one record and return value from given column
	 *
	 * @param string $field
	 * @param int|float|string|array|Where|null $where
	 * @param string|null $orderField
	 * @param string|null $orderDirection
	 *
	 * @return mixed
	 * @throws NotFoundException
	 * @throws Query\Exception
	 * @throws \Koldy\Exception
	 */
    public static function fetchOneValueOrFail(string $field, int | float | string | array | Where | null $where = null, string $orderField = null, string $orderDirection = null): mixed
    {
        $value = static::fetchOneValue($field, $where, $orderField, $orderDirection);

        if ($value === null) {
            throw new NotFoundException('Value not found');
        }

        return $value;
    }

	/**
	 * Check if some value exists in database or not. This is useful if you
	 * want, for an example, check if user's e-mail already is in database
	 * before you try to insert your data.
	 *
	 * @param  string $field
	 * @param  int|float|string $value
	 * @param  int|float|string|null $exceptionValue OPTIONAL
	 * @param  string|null $exceptionField OPTIONAL
	 *
	 * @return bool
	 * @throws Query\Exception
	 * @throws \Koldy\Exception
	 * @link http://koldy.net/docs/database/models#isUnique
	 *
	 * @example
	 *          User::isUnique('email', 'email@domain.com'); will execute:
	 *          SELECT COUNT(*) FROM user WHERE email = 'email@domain.com'
	 *
	 *          User::isUnique('email', 'email@domain.com', 'other@mail.com');
	 *          SELECT COUNT(*) FROM user WHERE email = 'email@domain.com' AND email != 'other@mail.com'
	 *
	 *          User::isUnique('email', 'email@domain.com', 5, 'id');
	 *          SELECT COUNT(*) FROM user WHERE email = 'email@domain.com' AND id != 5
	 */
    public static function isUnique(
      string $field,
      int | float | string $value,
      int | float | string $exceptionValue = null,
      string $exceptionField = null
    ): bool {
        $select = static::select();
        $select->field('COUNT(*)', 'total')->where($field, $value);

        if ($exceptionValue !== null) {
            if ($exceptionField === null) {
                $exceptionField = $field;
            }

            $select->where($exceptionField, '!=', $exceptionValue);
        }

        $results = $select->fetchAll();

        if (isset($results[0])) {
            return ($results[0]['total'] == 0);
        }

        return true;
    }

	/**
	 * Count the records in table according to the parameters
	 *
	 * @param int|float|string|array|Where|null $where
	 *
	 * @return int
	 * @throws Query\Exception
	 * @throws \Koldy\Exception
	 * @link http://koldy.net/docs/database/models#count
	 */
    public static function count(int | float | string | array | Where | null $where = null): int
    {
        $select = static::select();

        if ($where !== null) {
            if ($where instanceof Where) {
                $select->field('COUNT(*)', 'total');
                $select->where(clone $where);

            } else if (is_array($where)) {
                $select->field('COUNT(*)', 'total');
                foreach ($where as $field => $value) {
                    $select->where($field, $value);
                }

            } else if (!is_array(static::$primaryKey) && (is_numeric($where) || is_string($where))) {
                $select->field('COUNT(' . static::$primaryKey . ')', 'total');
                $select->where(static::$primaryKey, $where);

            }
        } else {
            $pk = is_string(static::$primaryKey) ? static::$primaryKey : '*';
            $select->field('COUNT(' . $pk . ')', 'total');
        }

        $results = $select->fetchAll();

        if (isset($results[0])) {
            $r = $results[0];
            if (array_key_exists('total', $r)) {
                return (int)$r['total'];
            } else {
                return 0;
            }
        } else {
            return 0;
        }
    }

	/**
	 * Get the ResultSet object of this model
	 *
	 * @param null|string $tableAlias
	 *
	 * @return ResultSet
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Exception
	 */
    public static function resultSet(string $tableAlias = null): ResultSet
    {
        $rs = new ResultSet(static::getTableName(), $tableAlias);
        $rs->setModelClass(get_called_class())->setAdapter(static::getAdapterConnection());
        return $rs;
    }

	/**
	 * Get the initialized Select object with populated FROM and connection adapter set
	 *
	 * @param string|null $tableAlias
	 *
	 * @return Select
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Exception
	 */
    public static function select(string $tableAlias = null): Select
    {
        $select = new Select(static::getTableName(), $tableAlias);
        $select->setAdapter(static::getAdapterConnection());
        return $select;
    }

	/**
	 * Get the initialized Insert instance with populated table name and connection adapter set
	 *
	 * @param array|null $rawValues
	 *
	 * @return Insert
	 * @throws Json\Exception
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Db\Exception
	 * @throws \Koldy\Exception
	 */
    public static function insert(array $rawValues = null): Insert
    {
        return new Insert(static::getTableName(), $rawValues, static::getAdapterConnection());
    }

    /**
     * @return string
     * @throws Exception
     * @throws Json\Exception
     */
    public function __toString(): string
    {
        $class = get_class($this);
        $stringify = Json::encode($this->getData());
        return "{$class}={$stringify}";
    }

    /**
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return array
     * @since 5.1.0
     */
    public function __serialize(): array
    {
        return [
          'data' => $this->data,
          'originalData' => $this->originalData
        ];
    }

    /**
     * Constructs the object
     * @link http://php.net/manual/en/serializable.unserialize.php
     *
     * @param array $data <p>
     * The string representation of the object.
     * </p>
     *
     * @return void
     * @since 5.1.0
     */
    public function __unserialize(array $data): void
    {
        $this->data = $data['data'];
        $this->setOriginalData($data['originalData']);
    }

}
