<?php declare(strict_types = 1);

namespace Koldy\Db;

use Koldy\Db;
use Koldy\Db\Adapter\AbstractAdapter;
use Koldy\Db\Exception\NotFoundException;
use Koldy\Db\Query\{
  Select, Insert, Update, Delete, ResultSet
};
use Koldy\Json;
use Serializable;

/**
 * Model is abstract class that needs to be extended with your defined class.
 * When you extend it, framework will know with which table it needs to work; or
 * simply define the table name you need. Check out the docs in link for more examples.
 *
 * @link http://koldy.net/docs/database/models
 */
abstract class Model implements Serializable
{

    /**
     * The DB adapter key from config on which the queries will be executed
     *
     * @var string
     */
    protected static $adapter = null;

    /**
     * If you don't define the table name, the framework will assume the table
     * name by the called class name. Class \Db\User\Roles that extends this will try to use db_user_roles table in DB
     *
     * @var string
     */
    protected static $table = null;

    /**
     * While working with tables in database, framework will always assume that
     * you have the field named "id" as unique identifier. If you have
     * your primary key with different name, please define it in the child
     * class.
     *
     * @var string|array
     */
    protected static $primaryKey = 'id';

    /**
     * Assume that this table has auto increment field and that field is primary field.
     * @var bool
     */
    protected static $autoIncrement = true;

    /**
     * The array of fields that will never be injected into query when calling
     * the save() method. Be aware that this doesn't work if you're calling static update() method
     * method.
     *
     * @var array
     */
    protected static $neverUpdate = [];

    /**
     * The data holder in this object
     *
     * @var array
     */
    private $data = null;

    /**
     * This is the array that holds information loaded from database. When
     * you call save() method, this data will be compared to the data set in
     * object and update method will set only fields that are changed. If there
     * is no change, update() method will return 0 without triggering query on
     * database.
     *
     * @var array
     */
    private $originalData = null;

    /**
     * Construct the instance with or without starting data
     *
     * @param array $data
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
     * @param $property
     *
     * @return mixed|null
     * @throws Exception
     */
    final public function __get(string $property)
    {
        if ($this->data === null) {
            $class = get_class($this);
            throw new Exception("Can not use __get() on {$class} because data wasn't set yet");
        }

        return $this->data[$property] ?? null;
    }

    /**
     * @param $property
     * @param $value
     */
    final public function __set(string $property, $value)
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
     * @return Model
     */
    final public function setData(array $values): Model
    {
        $this->data = array_merge($this->data, $values);
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
            throw new Exception("Can not use getData() on {$class} because data wasn't set");
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
     * @param array $values
     *
     * @return Model
     */
    protected function setOriginalData(array $values): Model
    {
        $this->originalData = $values;
        return $this;
    }

    /**
     * Manually set the adapter
     *
     * @param string $adapter
     */
    public static function setAdapterConnection(string $adapter)
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
     */
    public static function getAdapter(): AbstractAdapter
    {
        return Db::getAdapter(static::getAdapterConnection());
    }

    /**
     * Begin transaction using this model's DB adapter
     */
    public static function beginTransaction(): void
    {
        static::getAdapter()->beginTransaction();
    }

    /**
     * Commit current transaction using this model's DB adapter
     */
    public static function commit(): void
    {
        static::getAdapter()->commit();
    }

    /**
     * Rollback current transaction on this model's DB adapter
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
     * @param array $data pass array or valid instance of \Koldy\Db\Model
     *
     * @return Model|false False if insert failed, otherwise, instance of this model
     * @throws \Koldy\Exception
     */
    public static function create(array $data): Model
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
     * If you statically created new record in database to the table with auto
     * incrementing field, then you can use this static method to get the
     * generated primary key
     *
     * @param null|string $keyName
     *
     * @return int|string
     * @throws Exception
     * @example
     *
     *    if (User::create(array('first_name' => 'John', 'last_name' => 'Doe'))) {
     *      echo User::getLastInsertId();
     *    }
     */
    public static function getLastInsertId(string $keyName = null)
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
     * @param  mixed $where OPTIONAL if you pass single value, framework will
     * assume that you passed primary key value. If you pass assoc array,
     * then the framework will use those to create the WHERE statement.
     *
     * @example
     *
     *    User::update(array('first_name' => 'new name'), 5) will execute:
     *    UPDATE user SET first_name = 'new name' WHERE id = 5
     *
     *    User::update(array('first_name' => 'new name'), array('disabled' => 0)) will execute:
     *    UPDATE user SET first_name = 'new name' WHERE disabled = 0
     *
     * @return int number of affected rows
     */
    public static function update(array $data, $where = null): int
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
     * @throws Exception
     * @return integer how many rows is affected, -1 if nothing was updated
     */
    public function save(): int
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
     * @param mixed $where the primary key value of the record
     * @param int $howMuch default 1
     *
     * @throws Exception
     * @return int number of affected rows
     */
    public static function increment(string $field, $where, int $howMuch = 1): int
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
     * @param mixed $where
     *
     * @return integer How many records is deleted
     * @example User::delete(1);
     * @example User::delete(array('group_id' => 5, 'parent_id' => 10));
     * @example User::delete(array('parent_id' => 10, array('time', '>', '2013-08-01 00:00:00')))
     *
     * @return int number of affected rows
     * @link http://koldy.net/docs/database/models#delete
     */
    public static function delete($where): int
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
     * @throws Exception
     * @return int
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
     * If your criteria returns more then one records, only first record will
     * be taken.
     *
     * @param  mixed $field primaryKey value, single field or assoc array of arguments for query
     * @param  mixed $value
     * @param array $fields
     *
     * @throws Exception
     * @return Model|null null will be returned if record is not found
     * @link http://koldy.net/docs/database/models#fetchOne
     */
    public static function fetchOne($field, $value = null, array $fields = null): ?Model
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
     * If your criteria returns more then one records, only first record will
     * be taken.
     *
     * @param  mixed $field primaryKey value, single field or assoc array of arguments for query
     * @param  mixed $value
     * @param array $fields
     *
     * @throws Exception
     * @return Model|null null will be returned if record is not found
     * @link http://koldy.net/docs/database/models#fetchOne
     */
    public static function fetchOneOrFail($field, $value = null, array $fields = null): Model
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
     * @param mixed $where the WHERE condition
     * @param array $fields array of fields to select; by default, all fields will be fetched
     * @param string|null $orderField
     * @param string|null $orderDirection
     * @param int|null $start
     * @param int|null $limit
     *
     * @return Model[]
     *
     * @link http://koldy.net/docs/database/models#fetch
     */
    public static function fetch(
      $where,
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
            $select->where($where);
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
     * Fetch all records from database
     *
     * @param string $orderField
     * @param string $orderDirection
     *
     * @return Model[]
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
     * @param mixed $where
     * @param string|null $orderField
     * @param string|null $orderDirection
     * @param int|null $start
     * @param int|null $limit
     *
     * @return array
     * @link http://koldy.net/docs/database/models#fetchKeyValue
     */
    public static function fetchKeyValue(
      string $keyField,
      string $valueField,
      $where = null,
      $orderField = null,
      $orderDirection = null,
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
     * @param mixed $where
     * @param string $orderField
     * @param string $orderDirection
     * @param integer $limit
     * @param integer $start
     *
     * @return array or empty array if not found
     * @example User::fetchArrayOf('id', Where::init()->where('id', '>', 50), 'id', 'asc') would return array(51,52,53,54,55,...)
     * @link http://koldy.net/docs/database/models#fetchArrayOf
     */
    public static function fetchArrayOf(
      string $field,
      $where = null,
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
     * @param mixed|null $where
     * @param string|null $orderField
     * @param string|null $orderDirection
     *
     * @return mixed|null
     */
    public static function fetchOneValue(string $field, $where = null, $orderField = null, $orderDirection = null)
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
     * @param mixed|null $where
     * @param string|null $orderField
     * @param string|null $orderDirection
     *
     * @return mixed|null
     * @throws NotFoundException
     */
    public static function fetchOneValueOrFail(string $field, $where = null, $orderField = null, $orderDirection = null)
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
     * @param  mixed $value
     * @param  mixed $exceptionValue OPTIONAL
     * @param  string $exceptionField OPTIONAL
     *
     * @return bool|null
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
      $value,
      string $exceptionValue = null,
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
     * @param mixed $where
     *
     * @return int
     * @link http://koldy.net/docs/database/models#count
     */
    public static function count($where = null): int
    {
        $select = static::select();

        if ($where !== null) {
            if ($where instanceof Where) {
                $select->field('COUNT(*)', 'total');
                $select->where($where);

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
     * @return ResultSet
     */
    public static function resultSet(): ResultSet
    {
        $rs = new ResultSet(static::getTableName());
        $rs->setModelClass(get_called_class())->setAdapter(static::getAdapterConnection());
        return $rs;
    }

    /**
     * Get the initialized Select object with populated FROM part
     *
     * @param string $tableAlias
     *
     * @return Select
     */
    public static function select(string $tableAlias = null): Select
    {
        if ($tableAlias === null) {
            return new Select(static::getTableName(), static::getAdapterConnection());
        } else {
            $select = new Select(null, static::getAdapterConnection());
            $select->from(static::getTableName(), $tableAlias);
            return $select;
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return Json::encode($this->getData());
    }

    /**
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1.0
     */
    public function serialize()
    {
        return serialize([
          'data' => $this->data,
          'originalData' => $this->originalData
        ]);
    }

    /**
     * Constructs the object
     * @link http://php.net/manual/en/serializable.unserialize.php
     *
     * @param string $serialized <p>
     * The string representation of the object.
     * </p>
     *
     * @return void
     * @since 5.1.0
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized);
        $this->data = $data['data'];
        $this->setOriginalData($data['originalData']);
    }

}
