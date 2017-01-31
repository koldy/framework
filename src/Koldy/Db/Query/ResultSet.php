<?php declare(strict_types = 1);

namespace Koldy\Db\Query;

use Koldy\Db\Adapter\{
  PostgreSQL, Sqlite
};
use Koldy\Db\{
  Where, Query
};

/**
 * The ResultSet class needs to handle data sets fetched from database ready
 * for pagination and searching. Long story short, you can easily create
 * DataTables to work with this simply by passing the ResultSet instance.
 *
 */
class ResultSet extends Select
{

    /**
     * @var Select
     */
    protected $countQuery = null;

    /**
     * The search string
     *
     * @var string
     */
    protected $searchTerm = null;

    /**
     * The fields on which search will be performed - if not set, search
     * will be performed on all fields
     *
     * @var array
     */
    protected $searchFields = null;

    /**
     * Set the custom count query. If you're working with custom count query,
     * then you must handle the search terms by yourself. Think about overriding this method.
     *
     * @param Select $query
     *
     * @return ResultSet
     */
    public function setCountQuery(Select $query): ResultSet
    {
        $this->countQuery = $query;
        return $this;
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

        if ($searchFields !== null) {
            $query->setSearchFields($searchFields);
        }

        return $query;
    }

    /**
     * Set search fields
     *
     * @param array $fields
     *
     * @return ResultSet
     */
    public function setSearchFields(array $fields): ResultSet
    {
        $this->searchFields = $fields;
        return $this;
    }

    /**
     * Search for the fields
     *
     * @param string $searchText
     *
     * @return ResultSet
     */
    public function search(string $searchText): ResultSet
    {
        $this->searchTerm = $searchText;
        return $this;
    }

    /**
     * Count results
     *
     * @return int
     */
    public function count(): int
    {
        $result = $this->getCountQuery()->fetchAll();

        if (count($result) == 1) {
            return (int)$result[0]['total'];
        }

        return 0;
    }

    /**
     * @return Query
     */
    protected function getQuery(): Query
    {
        if ($this->searchTerm !== null) {

            // there is search term set, so we'll need to include this to where statements

            // but, there might be already some where statements in the query, so we'll create
            // new Where instance and we'll add that Where block with AND operator

            $adapter = $this->getAdapter();
            if ($adapter instanceof PostgreSQL) {
                $useILike = true;
            } else if ($adapter instanceof Sqlite) {
                // create ILIKE


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

}
