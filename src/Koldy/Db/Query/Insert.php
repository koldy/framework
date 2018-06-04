<?php declare(strict_types=1);

namespace Koldy\Db\Query;

use Koldy\Db\{
  Exception, Query, Expr
};
use Koldy\Json;

/**
 * Use this class if you want to insert multiple rows at once
 * @link http://koldy.net/docs/database/query-builder#insert
 *
 */
class Insert
{

    use Statement;

    /**
     * The table on which insert will be executed
     *
     * @var string
     */
    protected $table = null;

    /**
     * The fields on which will data be inserted
     *
     * @var array
     */
    protected $fields = [];

    /**
     * Array of rows of data
     *
     * @var array
     */
    protected $data = [];

    /**
     * For INSERT INTO () SELECT statements
     *
     * @var Select|string
     */
    protected $select = null;

    /**
     * Construct the object
     *
     * @param string|null $table
     * @param array $rowValues is key => value array to insert into database
     * @param string|null $adapter
     *
     * @throws Exception
     * @throws Json\Exception
     * @link http://koldy.net/docs/database/query-builder#insert
     */
    public function __construct(string $table = null, array $rowValues = null, string $adapter = null)
    {
        if ($table !== null) {
            $this->into($table);
        }

        if ($rowValues != null) {
            if (isset($rowValues[0]) && is_array($rowValues[0])) {
                $this->addRows($rowValues);
            } else {
                foreach ($rowValues as $field => $value) {
                    if (is_numeric($field)) {
                        $json = Json::encode($rowValues);
                        throw new Exception("Unable to add field name to Insert SQL statement; please check the 2nd argument; framework got: {$json}");
                    }
                    $this->field($field);
                }
                $this->add($rowValues);
            }
        }

        if ($adapter !== null) {
            $this->setAdapter($adapter);
        }
    }

    /**
     * The table on which insert will be executed
     *
     * @param string $table
     *
     * @return Insert
     * @deprecated Deprecated since v2.0. Use \Koldy\Db\Query\Insert::into() instead of table() method.
     */
    public function table(string $table): Insert
    {
        return $this->into($table);
    }

    /**
     * The table on which insert will be executed
     *
     * @param string $table
     *
     * @return Insert
     */
    public function into(string $table): Insert
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Set one field on which query will be executed
     *
     * @param string $field
     *
     * @return Insert
     */
    public function field(string $field): Insert
    {
        $this->fields[] = $field;
        return $this;
    }

    /**
     * Set the fields that will be inserted
     *
     * @param array $fields
     *
     * @return Insert
     */
    public function fields(array $fields): Insert
    {
        $this->fields = array_values($fields);
        return $this;
    }

    /**
     * Add row
     *
     * @param array $data array of values in row
     *
     * @return Insert
     */
    public function add(array $data): Insert
    {
        if (!isset($data[0]) && count($this->fields) == 0) {
            $this->fields(array_keys($data));
        }

        $this->data[] = $data;
        return $this;
    }

    /**
     * Return if there's any data added using add(), addRows() or through constructor on $rowValues
     *
     * @return bool
     */
    public function hasDataToInsert(): bool
    {
        return $this->countDataToInsert() > 0;
    }

    /**
     * Get how many records is set to be inserted in database table
     *
     * @return int
     */
    public function countDataToInsert(): int
    {
        return count($this->data);
    }

    /**
     * Add multiple rows into insert
     *
     * @param array $rows
     *
     * @return Insert
     */
    public function addRows(array $rows): Insert
    {
        foreach ($rows as $index => $row) {
            $this->add($row);
        }
        return $this;
    }

    /**
     * The select query to insert from
     *
     * @param Select|string $selectQuery
     *
     * @return Insert
     */
    public function selectFrom($selectQuery): Insert
    {
        $this->select = $selectQuery;
        return $this;
    }

    /**
     * Get the Query string
     *
     * @throws Exception
     * @return Query
     */
    public function getQuery(): Query
    {
        $bindings = [];

        if (count($this->data) == 0 && $this->select === null) {
            throw new Exception('Can not execute Insert query, no records to insert');
        }

        if ($this->table === null) {
            throw new Exception('Can not execute Insert query when table name is not set');
        }

        $hasFields = count($this->fields) > 0;

        $query = "INSERT INTO {$this->table}";

        if ($hasFields) {
            $query .= ' (';

            foreach ($this->fields as $field) {
                $query .= $field . ',';
            }

            $query = substr($query, 0, -1) . ')';
        }

        if ($this->select !== null) {
            if ($this->select instanceof Select || $this->select instanceof Query) {
                $query .= "\n(\n\t" . str_replace("\n", "\n\t", $this->select->__toString()) . "\n)";
                $bindings = $this->select->getBindings();
            } else {
                throw new Exception('Can not use non-Select or non-Query instance in INSERT INTO SELECT statement - use Query instance to pass Select query');
            }
        } else {
            $query .= "\nVALUES\n";
            foreach ($this->data as $i1 => $row) {
                $query .= "\t(";

                if ($hasFields) {

                    foreach ($this->fields as $field) {
                        if (isset($row[$field])) {
                            $val = $row[$field];

                            if ($val instanceof Expr) {
                                $query .= "{$val},";
                            } else {
                                $key = 'i' . $i1 . Query::getKeyIndex();
                                $query .= ":{$key},";
                                $bindings[$key] = $val;
                            }
                        } else {
                            $query .= 'NULL,';
                        }
                    }

                    $query = substr($query, 0, -1);

                } else {
                    $values = array_values($row);

                    foreach ($values as $i2 => $val) {
                        if ($val instanceof Expr) {
                            $query .= "{$val},";
                        } else {
                            $key = 'i' . $i2 . Query::getKeyIndex();
                            $query .= ":{$key},";
                            $bindings[$key] = $val;
                        }
                    }

                    $query = substr($query, 0, -1);
                }

                $query .= "),\n";
            }

            $query = substr($query, 0, -2);
        }

        return new Query($query, $bindings, $this->getAdapterConnection());
    }

}
