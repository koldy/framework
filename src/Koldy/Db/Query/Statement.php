<?php declare(strict_types=1);

namespace Koldy\Db\Query;

use Koldy\Db;
use Koldy\Db\Query;
use Koldy\Db\Adapter\AbstractAdapter;

trait Statement
{

    /**
     * @var string|null
     */
    protected string | null $adapter = null;

    /**
     * @var Query|null
     */
    private Query | null $lastQuery = null;

	/**
	 * Get the adapter on which query will be performed
	 * @return AbstractAdapter
	 * @throws Db\Exception
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Exception
	 */
    public function getAdapter(): AbstractAdapter
    {
        return Db::getAdapter($this->adapter);
    }

    /**
     * Get the adapter connection key from config that will be used
     *
     * @return null|string
     */
    public function getAdapterConnection(): ?string
    {
        return $this->adapter;
    }

	/**
	 * @param string|null $adapter
	 *
	 * @return static
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Exception
	 */
    public function setAdapter(string|null $adapter = null): static
    {
        $this->adapter = $adapter ?? Db::getDefaultAdapterKey();
        return $this;
    }

    /**
     * Is database adapter explicitly set or not?
     *
     * @return bool
     */
    public function hasAdapterSet(): bool
    {
        return $this->adapter !== null;
    }

    /**
     * Get generated query
     *
     * @return Query
     */
    abstract public function getQuery(): Query;

	/**
	 * Get SQL prepared for PDO, before binding real values
	 *
	 * @return string
	 * @throws Db\Exception
	 * @throws Exception
	 * @throws \Koldy\Exception
	 */
    public function getSQL(): string
    {
        return $this->getQuery()->getSQL();
    }

    /**
     * Execute the query
     *
     * @param string|null $adapter
     *
     * @return static
     * @throws Exception
     * @throws \Koldy\Exception
     */
    public function exec(string|null $adapter = null): static
    {
        $this->lastQuery = $this->getQuery();

        if ($adapter !== null) {
            $this->lastQuery->setAdapterConnection($adapter);
        }

        $this->lastQuery->exec();
        return $this;
    }

    /**
     * @return bool
     */
    public function wasExecuted(): bool
    {
        return $this->lastQuery instanceof Query && $this->lastQuery->wasExecuted();
    }

    /**
     * Reset information about last executed query, which will allow query to run again (needed when cloning)
     */
    protected function resetLastQuery(): void
    {
        $this->lastQuery = null;
    }

    /**
     * @return Query|null
     */
    public function getLastQuery(): ?Query
    {
        return $this->lastQuery;
    }

    /**
     * @param bool $oneLine
     *
     * @return string
     * @throws Exception
     * @throws \Koldy\Exception
     */
    public function debug(bool $oneLine = false): string
    {
        return $this->getQuery()->debug($oneLine);
    }

    /**
     * @return string
     * @throws Exception
     * @throws \Koldy\Exception
     */
    public function __toString()
    {
        return $this->debug(true);
    }

}
