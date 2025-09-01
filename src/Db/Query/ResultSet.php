<?php declare(strict_types=1);

namespace Koldy\Db\Query;

use Closure;
use Koldy\Db\{Query, Where};
use Koldy\Db\Adapter\{PostgreSQL, Sqlite};

/**
 * The ResultSet class needs to handle data sets fetched from database ready
 * for pagination and searching. Long story short, you can easily create
 * DataTables to work with this simply by passing the ResultSet instance.
 *
 */
class ResultSet extends Select
{

	protected Select|null $countQuery = null;

	/**
	 * Precalculated count
	 *
	 * @var null|int
	 */
	protected int|null $count = null;

	/**
	 * @var null|Closure
	 */
	protected Closure|null $countQueryAdjustableFunction = null;

	/**
	 * The search string
	 *
	 * @var string|null
	 */
	protected string|null $searchTerm = null;

	/**
	 * The fields on which search will be performed - if not set, search
	 * will be performed on all fields
	 *
	 * @var array|null
	 */
	protected array|null $searchFields = null;

	/**
	 * The model's class name
	 *
	 * @var string|null
	 */
	protected string|null $modelClass = null;

	/**
	 * @var bool
	 */
	protected bool $resetGroupBy = false;

	/**
	 * @return null|string
	 */
	public function getModelClass(): ?string
	{
		return $this->modelClass;
	}

	/**
	 * @param string $modelClass
	 *
	 * @return static
	 */
	public function setModelClass(string $modelClass): static
	{
		$this->modelClass = $modelClass;
		return $this;
	}

	/**
	 * Execute adjustments on count query when needed. First parameter of given function will be instance of Select.
	 *
	 * If it's used in combination with setCountQuery, then you'll get that query as parameter which doesn't have much
	 * sense.
	 *
	 * @param Closure $fn
	 */
	public function adjustCountQuery(Closure $fn): void
	{
		$this->countQueryAdjustableFunction = $fn;
	}

	/**
	 * @return bool
	 */
	public function hasSearchField(): bool
	{
		return is_array($this->searchFields) && count($this->searchFields) > 0;
	}

	/**
	 * @return static
	 */
	public function resetGroupByOnCount(): static
	{
		$this->resetGroupBy = true;
		return $this;
	}

	/**
	 * Search for the fields
	 *
	 * @param string $searchText
	 *
	 * @return static
	 */
	public function search(string $searchText): static
	{
		$this->searchTerm = $searchText;
		return $this;
	}

	/**
	 * Count results
	 *
	 * @return int
	 * @throws Exception
	 * @throws \Koldy\Exception
	 */
	public function count(): int
	{
		if ($this->count !== null) {
			return $this->count;
		}

		$select = $this->getCountQuery();

		if ($this->countQueryAdjustableFunction !== null) {
			$select = call_user_func($this->countQueryAdjustableFunction, $select);
		}

		$result = $select->fetchFirst();

		if ($result === null) {
			return 0;
		}

		$this->count = (int)$result['total'];
		return $this->count;
	}

	/**
	 * Get SELECT query for total count
	 *
	 * @return Select
	 */
	protected function getCountQuery(): Select
	{
		if ($this->countQuery instanceof Select) {
			return $this->countQuery;
		}

		if ($this->searchTerm !== null && $this->searchFields === null) {
			$fields = $this->getFields();
			$searchFields = [];
			foreach ($fields as $field) {
				$searchFields[] = $field['name'];
			}
		} else {
			$searchFields = null;
		}

		$query = clone $this;
		$query->resetFields();
		$query->resetLimit();
		$query->resetOrderBy();
		$query->field('COUNT(*)', 'total');

		if ($this->resetGroupBy) {
			$query->resetGroupBy();
		}

		if ($searchFields !== null) {
			$query->setSearchFields($searchFields);
		}

		return $query;
	}

	/**
	 * Set the custom count query. If you're working with custom count query,
	 * then you must handle the search terms by yourself. Think about overriding this method.
	 *
	 * @param Select $query
	 *
	 * @return static
	 */
	public function setCountQuery(Select $query): static
	{
		$this->countQuery = $query;
		return $this;
	}

	/**
	 * Set search fields
	 *
	 * @param array $fields
	 *
	 * @return static
	 */
	public function setSearchFields(array $fields): static
	{
		$this->searchFields = $fields;
		return $this;
	}

	/**
	 * @param int|null $count
	 */
	public function setCount(?int $count): void
	{
		$this->count = $count;
	}

	/**
	 * @return Query
	 * @throws Exception
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Db\Exception
	 * @throws \Koldy\Exception
	 */
	public function getQuery(): Query
	{
		if ($this->searchTerm !== null) {

			// there is search term set, so we'll need to include this to where statements

			// but, there might be already some where statements in the query, so we'll create
			// new Where instance and we'll add that Where block with AND operator

			$adapter = $this->getAdapter();
			if ($adapter instanceof PostgreSQL) {
				$useILike = true;
			} else if ($adapter instanceof Sqlite) {
				// ILIKE was created when "connection" was opened
				$useILike = true;
			} else {
				$useILike = false;
			}

			$where = Where::init();
			if ($this->searchFields !== null) {
				foreach ($this->searchFields as $field) {
					if ($useILike) {
						$where->orWhere($field, 'ILIKE', "%{$this->searchTerm}%");
					} else {
						$where->orWhereLike($field, "%{$this->searchTerm}%");
					}
				}
			} else {
				foreach ($this->getFields() as $field) {
					if ($useILike) {
						$where->orWhereLike($field['name'], "%{$this->searchTerm}%");
					} else {
						$where->orWhere($field['name'], 'ILIKE', "%{$this->searchTerm}%");
					}
				}
			}
			$this->where($where);
		}

		return parent::getQuery();
	}

	/**
	 * Fetch all records as array of objects
	 *
	 * @param string|null $class the name of class on which you want the instance of - class has to be able to accept
	 *     array in constructor
	 *
	 * @return array
	 * @throws Exception
	 * @throws \Koldy\Exception
	 */
	public function fetchAllObj(string|null $class = null): array
	{
		if ($class == null && $this->modelClass !== null) {
			return parent::fetchAllObj($this->modelClass);
		} else {
			return parent::fetchAllObj($class);
		}
	}

	/**
	 * Fetch only first record as object
	 *
	 * @param string|null $class
	 *
	 * @return null|object
	 * @throws Exception
	 * @throws \Koldy\Exception
	 */
	public function fetchFirstObj(string|null $class = null): ?object
	{
		if ($class == null && $this->modelClass !== null) {
			return parent::fetchFirstObj($this->modelClass);
		} else {
			return parent::fetchFirstObj($class);
		}
	}

}
