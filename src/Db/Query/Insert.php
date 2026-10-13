<?php declare(strict_types=1);

namespace Koldy\Db\Query;

use Koldy\Db\{Exception, Expr, Query};
use Koldy\Json;

/**
 * Use this class if you want to insert multiple rows at once
 * @link http://koldy.net/docs/database/query-builder#insert
 *
 */
class Insert
{

	use Statement {
		exec as private statementExec;
	}

	/**
	 * The table on which insert will be executed
	 *
	 * @var string|null
	 */
	protected string|null $table = null;

	/**
	 * The fields on which will data be inserted
	 *
	 * @var array
	 */
	protected array $fields = [];

	/**
	 * Array of rows of data
	 *
	 * @var array
	 */
	protected array $data = [];

	/**
	 * For INSERT INTO () SELECT statements
	 *
	 * @var Select|string|null
	 */
	protected Select|string|null $select = null;

	/**
	 * Columns requested through the INSERT ... RETURNING clause, already stringified. Null means the clause was not
	 * requested at all.
	 *
	 * @var string[]|null
	 */
	protected array|null $returning = null;

	/**
	 * Rows returned by the database for the RETURNING clause of the last exec()
	 *
	 * @var array
	 */
	private array $returnedRows = [];

	/**
	 * Construct the object
	 *
	 * @param string|null $table
	 * @param array|null $rowValues is key => value array to insert into database
	 * @param string|null $adapter
	 *
	 * @throws Exception
	 * @throws Json\Exception
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Exception
	 * @link http://koldy.net/docs/database/query-builder#insert
	 */
	public function __construct(string|null $table = null, array|null $rowValues = null, string|null $adapter = null)
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
	 * @return static
	 */
	public function into(string $table): static
	{
		$this->table = $table;
		return $this;
	}

	/**
	 * Add multiple rows into insert
	 *
	 * @param array $rows
	 *
	 * @return static
	 */
	public function addRows(array $rows): static
	{
		foreach ($rows as $row) {
			$this->add($row);
		}
		return $this;
	}

	/**
	 * Add row
	 *
	 * @param array $data array of values in row
	 *
	 * @return static
	 */
	public function add(array $data): static
	{
		if (!isset($data[0]) && count($this->fields) == 0) {
			$this->fields(array_keys($data));
		}

		$this->data[] = $data;
		return $this;
	}

	/**
	 * Set the fields that will be inserted
	 *
	 * @param array $fields
	 *
	 * @return static
	 */
	public function fields(array $fields): static
	{
		$this->fields = array_values($fields);
		return $this;
	}

	/**
	 * Set one field on which query will be executed
	 *
	 * @param string $field
	 *
	 * @return static
	 */
	public function field(string $field): static
	{
		$this->fields[] = $field;
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
	 * The select query to insert from
	 *
	 * @param string|Select $selectQuery
	 *
	 * @return static
	 */
	public function selectFrom(string|Select $selectQuery): static
	{
		$this->select = $selectQuery;
		return $this;
	}

	/**
	 * Request the INSERT ... RETURNING clause so the database sends back the inserted row(s) in the same roundtrip.
	 * Call it without arguments to get all columns (RETURNING *), or pass column names and/or expressions.
	 *
	 * The clause is emitted only when the adapter supports it (PostgreSQL, MariaDB 10.5+, SQLite 3.35+); on other
	 * adapters (MySQL) it is silently dropped and getReturnedRows() stays empty, so the calling code remains portable.
	 * Because of that, once returning() was called, getSQL(), debug() and __toString() resolve the adapter from config
	 * (and, for SQLite, open the connection) to decide whether the clause is part of the SQL.
	 *
	 * Column names are interpolated into SQL as-is, exactly like fields(); never pass user input here.
	 *
	 * @param string|Expr ...$fields
	 *
	 * @return static
	 * @link http://koldy.net/docs/database/query-builder#insert
	 */
	public function returning(string|Expr ...$fields): static
	{
		if (count($fields) === 0) {
			$this->returning = ['*'];
		} else {
			$this->returning = array_map(static fn (string|Expr $field): string => (string)$field, $fields);
		}

		return $this;
	}

	/**
	 * Get all rows the database returned for the RETURNING clause of the last exec(). Empty when exec() wasn't called
	 * yet, when returning() wasn't requested, or when the adapter doesn't support RETURNING.
	 *
	 * @return array
	 */
	public function getReturnedRows(): array
	{
		return $this->returnedRows;
	}

	/**
	 * Get the first row the database returned for the RETURNING clause of the last exec(), or null if there's none
	 *
	 * @return array|null
	 */
	public function getReturnedRow(): array|null
	{
		return $this->returnedRows[0] ?? null;
	}

	/**
	 * Execute the query and, if RETURNING was requested and the adapter supports it, fetch the returned rows right
	 * away. The rows must be read eagerly: the PDO statement lives on the shared adapter and would be replaced by the
	 * next query on the same connection, and MySQL-compatible adapters run unbuffered.
	 *
	 * Like in other query builders, passing $adapter runs this one execution on that adapter; the Insert keeps its own
	 * adapter afterwards (use setAdapter() to re-target it permanently).
	 *
	 * @param string|null $adapter
	 *
	 * @return static
	 * @throws Exception
	 * @throws \Koldy\Exception
	 */
	public function exec(string|null $adapter = null): static
	{
		// getQuery() asks this Insert's adapter whether RETURNING is supported, so the one-off adapter has to be in
		// place while the query is built and executed; the Insert's own adapter is restored afterwards
		$ownAdapter = $this->adapter;

		if ($adapter !== null) {
			$this->adapter = $adapter;
		}

		try {
			$fetchReturned = $this->returning !== null && $this->getAdapter()->supportsReturning();
			$this->returnedRows = [];

			$this->statementExec();

			$lastQuery = $this->getLastQuery();
			if ($fetchReturned && $lastQuery !== null) {
				$this->returnedRows = $lastQuery->fetchAll();
			}
		} finally {
			$this->adapter = $ownAdapter;
		}

		return $this;
	}

	/**
	 * A cloned Insert can be executed again, so forget the previous execution and its returned rows
	 */
	public function __clone()
	{
		$this->resetLastQuery();
		$this->returnedRows = [];
	}

	/**
	 * Get the Query string
	 *
	 * @return Query
	 * @throws Exception
	 * @throws \Koldy\Db\Query\Exception
	 * @throws \Koldy\Exception
	 */
	public function getQuery(): Query
	{
		$bindings = new Bindings();

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
			// @phpstan-ignore-next-line
			if ($this->select instanceof Select || $this->select instanceof Query) {
				$query .= "\n(\n\t" . str_replace("\n", "\n\t", $this->select->__toString()) . "\n)";
				$bindings->addBindingsFromInstance($this->select->getBindings());
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
								//$key = 'i' . $i1 . Query::getKeyIndex();
								$key = $bindings->makeAndSet("i{$i1}", $val);
								$query .= ":{$key},";
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
							//$key = 'i' . $i2 . Query::getKeyIndex();
							$key = $bindings->makeAndSet("i{$i2}", $val);
							$query .= ":{$key},";
						}
					}

					$query = substr($query, 0, -1);
				}

				$query .= "),\n";
			}

			$query = substr($query, 0, -2);
		}

		if ($this->returning !== null && $this->getAdapter()->supportsReturning()) {
			$query .= "\nRETURNING " . implode(', ', $this->returning);
		}

		return new Query($query, $bindings, $this->getAdapterConnection());
	}

}
