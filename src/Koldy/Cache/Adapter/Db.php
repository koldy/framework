<?php declare(strict_types=1);

namespace Koldy\Cache\Adapter;

use Closure;
use Koldy\Db as DbAdapter;
use Koldy\Db\Adapter\AbstractAdapter;
use Koldy\Db\Exception as DbException;
use Koldy\Log;
use Koldy\Db\Query\{
  Select, Insert, Update, Delete
};
use Koldy\Cache\Exception as CacheException;

/**
 * This cache adapter will store your cache data into database.
 *
 * @link https://koldy.net/framework/docs/2.0/cache/database.md
 */
class Db extends AbstractCacheAdapter
{

    /**
     * @return string
     */
    protected function getTableName(): string
    {
        return $this->config['table'] ?? 'cache';
    }

    /**
     * @return null|string
     */
    protected function getAdapterConnection(): ?string
    {
        return $this->config['adapter'] ?? null;
    }

	/**
	 * @return AbstractAdapter
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Exception
	 */
    protected function getAdapter(): AbstractAdapter
    {
        return DbAdapter::getAdapter($this->getAdapterConnection());
    }

    /**
     * @param string $key
     *
     * @return string
     */
    private function getKeyName(string $key): string
    {
        $this->checkKey($key);
        return $key;
    }

    /**
     * @param string $key
     *
     * @return mixed|null
     * @throws \Koldy\Db\Query\Exception
     * @throws \Koldy\Exception
     */
    public function get(string $key): mixed
    {
        $key = $this->getKeyName($key);

        $select = new Select($this->getTableName());
        $select->setAdapter($this->getAdapterConnection());
        $select->where('id', $key)->where('expires_at', '>', time());

        $record = $select->fetchFirst();

        if ($record === null) {
            return null;
        } else {
            return unserialize($record['data']);
        }
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int|null $seconds
     *
     * @throws \Koldy\Db\Query\Exception
     * @throws \Koldy\Exception
     * @throws \Koldy\Json\Exception
     */
    public function set(string $key, mixed $value, int $seconds = null): void
    {
        $key = $this->getKeyName($key);

        if ($seconds === null) {
            $seconds = $this->defaultDuration;
        }

		try {
			$update = new Update($this->getTableName(), null, $this->getAdapterConnection());
			$update->set('expires_at', time() + $seconds);
			$update->set('data', serialize($value));
			$update->where('id', $key);
			$ok = $update->rowCount();

			if ($ok === 0 && !$this->has($key)) {
				$insert = new Insert($this->getTableName(), null, $this->getAdapterConnection());
				$insert->add([
					'id' => $key,
					'expires_at' => time() + $seconds,
					'data' => serialize($value)
				]);
				$insert->exec();
			}
		} catch (DbException $e) {
			throw new CacheException("Couldn't store value(s) to cache key \"{$key}\" because it failed on database level: {$e->getMessage()}", $e->getCode(), $e);
		}
    }

    /**
     * @param string $key
     *
     * @return bool
     * @throws \Koldy\Db\Query\Exception
     * @throws \Koldy\Exception
     */
    public function has(string $key): bool
    {
        $key = $this->getKeyName($key);

        $select = new Select($this->getTableName());
        $select->setAdapter($this->getAdapterConnection());
        $select->field('id')->where('id', $key)->where('expires_at', '>', time());

        return $select->fetchFirst() !== null;
    }

    /**
     * @param string $key
     * @throws \Koldy\Db\Query\Exception
     * @throws \Koldy\Exception
     */
    public function delete(string $key): void
    {
		try {
			$key = $this->getKeyName($key);
			$delete = new Delete($this->getTableName(), $this->getAdapterConnection());
			$delete->where('id', $key)->exec();
		} catch (DbException $e) {
			throw new CacheException("Couldn't delete cache key \"{$key}\" because it failed on database level: {$e->getMessage()}", $e->getCode(), $e);
		}
    }

    /**
     * Deletes all cache
     *
     * @throws \Koldy\Db\Query\Exception
     * @throws \Koldy\Exception
     */
    public function deleteAll(): void
    {
	    try {
	        $delete = new Delete($this->getTableName(), $this->getAdapterConnection());
	        $delete->exec();
	    } catch (DbException $e) {
		    throw new CacheException("Couldn't delete all cache keys because it failed on database level: {$e->getMessage()}", $e->getCode(), $e);
	    }
    }

    /**
     * @param int|null $olderThanSeconds
     * @throws \Koldy\Db\Query\Exception
     * @throws \Koldy\Exception
     */
    public function deleteOld(int $olderThanSeconds = null): void
    {
		if ($olderThanSeconds === null) {
			$olderThanSeconds = $this->defaultDuration ?? 3600;
		}

		try {
			$delete = new Delete($this->getTableName(), $this->getAdapterConnection());
			$delete->where('expires_at', '<=', time() - $olderThanSeconds)->exec();
		} catch (DbException $e) {
			Log::warning('Couldn\'t delete old cache keys because it failed on database level', $e);
		}
    }

	/**
	 * Get the array of values from cache by given keys
	 *
	 * @param array $keys
	 *
	 * @return array value or null if key doesn't exists or cache is disabled
	 * @throws CacheException
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Db\Query\Exception
	 * @throws \Koldy\Exception
	 * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
	 */
    public function getMulti(array $keys): array
    {
        if (count($keys) == 0) {
            throw new CacheException('Can not use getMulti with empty set of keys');
        }

        $keys = array_values($keys);

        $serverKeys = [];
        foreach ($keys as $key) {
            $serverKeys[] = $this->getKeyName($key);
        }

        $select = new Select($this->getTableName());
        $select->setAdapter($this->getAdapterConnection());
        $select->field('id')->field('data')->where('expires_at', '<', time())->whereIn('id', $serverKeys);

        $serverValues = [];
        foreach ($select->fetchAll() as $r) {
            $serverValues[$r['id']] = unserialize($r['data']);
        }

        $result = [];
        foreach ($keys as $key) {
            $serverKey = $this->getKeyName($key);
            $result[$key] = $serverValues[$serverKey] ?? null;
        }

        return $result;
    }

    /**
     * Set multiple values to default cache engine and overwrite if keys already exists
     *
     * @param array $keyValuePairs
     * @param int|null $seconds
     *
     * @throws CacheException
     * @throws DbException
     * @throws \Koldy\Db\Query\Exception
     * @throws \Koldy\Exception
     * @throws \Koldy\Json\Exception
     * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
     */
    public function setMulti(array $keyValuePairs, int $seconds = null): void
    {
        if (count($keyValuePairs) == 0) {
            throw new CacheException('Can not use setMulti on empty array');
        }

        if ($seconds === null) {
            $seconds = $this->defaultDuration;
        }

        $serverKeyValuePairs = [];
        $insert = new Insert($this->getTableName(), null, $this->getAdapterConnection());

        foreach ($keyValuePairs as $key => $value) {
            $key = $this->getKeyName($key);
            $serverKeyValuePairs[$key] = $value;

            $insert->add([
              'id' => $key,
              'data' => serialize($value),
              'expires_at' => time() + $seconds
            ]);
        }

        $delete = new Delete($this->getTableName(), $this->getAdapterConnection());
        $delete->whereIn('id', array_keys($serverKeyValuePairs))->exec();
        $insert->exec();
    }

    /**
     * @param array $keys
     * @param Closure $functionOnMissingKeys
     * @param int|null $seconds
     *
     * @return array
     * @throws CacheException
     * @throws \Koldy\Db\Query\Exception
     * @throws \Koldy\Exception
     */
    public function getOrSetMulti(array $keys, Closure $functionOnMissingKeys, int $seconds = null): array
    {
        $found = $this->getMulti($keys);
        $missing = [];
        $return = [];

        foreach ($keys as $key) {
            $value = $this->get($key);

            if (!array_key_exists($key, $found)) {
                $missing[] = $key;
                $return[$key] = null;
            } else {
                $found[] = $key;
                $return[$key] = $value->data;
            }
        }

        if (count($missing) > 0) {
//        	try {
		        $setValues = call_user_func($functionOnMissingKeys, $found, $missing, $seconds);
//	        } catch (Exception | Throwable $e) {
//        		throw new CacheException("Unable to cache set of values because exception was thrown in setter function on missing keys: {$e->getMessage()}", $e->getCode(), $e);
//	        }

            if (!is_array($setValues)) {
                throw new CacheException('Return value from function passed to getOrSetMulti must return array; got ' . gettype($setValues));
            }

            $return = array_merge($return, $setValues);
        }

        return $return;
    }

    /**
     * Delete multiple items from cache engine
     *
     * @param array $keys
     *
     * @throws \Koldy\Db\Query\Exception
     * @throws \Koldy\Exception
     * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
     */
    public function deleteMulti(array $keys): void
    {
		try {
			$delete = new Delete($this->getTableName(), $this->getAdapterConnection());
			$delete->whereIn('id', $keys)->exec();
		} catch (DbException $e) {
			$keys = implode(', ', $keys);
			throw new CacheException("Couldn't delete multiple cache keys ({$keys}) because it failed on database level", $e->getCode(), $e);
		}
    }

}
