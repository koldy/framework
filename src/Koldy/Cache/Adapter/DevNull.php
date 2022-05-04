<?php declare(strict_types=1);

namespace Koldy\Cache\Adapter;

use Closure;
use Koldy\Cache\Exception as CacheException;

/**
 * If you don't want to use your cache adapter, you can redirect all cache data into black whole! Learn more at http://en.wikipedia.org/wiki//dev/null
 *
 * This class handles the cache adapter instance, but using it, nothing will happen. This class will be initialized if you try to use adapter that is disabled.
 *
 * @link https://koldy.net/framework/docs/2.0/cache/dev-null.md
 */
class DevNull extends AbstractCacheAdapter
{

    /**
     * @param string $key
     *
     * @return mixed|null
     */
    public function get(string $key): mixed
    {
        $this->checkKey($key);
        return null;
    }

    /**
     * Get the array of values from cache by given keys
     *
     * @param array $keys
     *
     * @return mixed[]
     * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
     */
    public function getMulti(array $keys): array
    {
        foreach ($keys as $key) {
            $this->checkKey($key);
        }

        return [];
    }

	/**
	 * @param string $key
	 * @param mixed $value
	 * @param int|null $seconds
	 */
    public function set(string $key, mixed $value, int $seconds = null): void
    {
        $this->checkKey($key);
    }

    /**
     * Set multiple values to default cache engine and overwrite if keys already exists
     *
     * @param array $keyValuePairs
     * @param int|null $seconds
     *
     * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
     */
    public function setMulti(array $keyValuePairs, int $seconds = null): void
    {
        foreach (array_keys($keyValuePairs) as $key) {
            $this->checkKey($key);
        }
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key): bool
    {
        $this->checkKey($key);
        return false;
    }

    /**
     * @param string $key
     */
    public function delete(string $key): void
    {
        $this->checkKey($key);
    }

    /**
     * Delete multiple items from cache engine
     *
     * @param array $keys
     *
     * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
     */
    public function deleteMulti(array $keys): void
    {
        foreach ($keys as $key) {
            $this->checkKey($key);
        }
    }

    /**
     * Delete all
     */
    public function deleteAll(): void
    {
        // nothing to delete
    }

    /**
     * @param int $olderThanSeconds
     */
    public function deleteOld(int $olderThanSeconds = null): void
    {
        // nothing to delete
    }

	/**
	 * @param string $key
	 * @param Closure $functionOnSet
	 * @param int|null $seconds
	 *
	 * @return mixed
	 * @throws CacheException
	 */
    public function getOrSet(string $key, Closure $functionOnSet, int $seconds = null): mixed
    {
        $this->checkKey($key);

	    try {
		    return call_user_func($functionOnSet, $key, $seconds);
	    } catch (\Exception | \Throwable $e) {
		    throw new CacheException("Unable to cache set of values because exception was thrown in setter function on missing keys: {$e->getMessage()}", $e->getCode(), $e);
	    }
    }

	/**
	 * @param array $keys
	 * @param Closure $functionOnMissingKeys
	 * @param int|null $seconds
	 *
	 * @return array
	 * @throws CacheException
	 */
    public function getOrSetMulti(array $keys, Closure $functionOnMissingKeys, int $seconds = null): array
    {
        foreach ($keys as $key) {
            $this->checkKey($key);
        }

	    try {
		    $values = call_user_func($functionOnMissingKeys, [], $keys, $seconds); // calling function, read keys, missing keys, seconds
	    } catch (\Exception | \Throwable $e) {
		    throw new CacheException("Unable to cache set of values because exception was thrown in setter function on missing keys: {$e->getMessage()}", $e->getCode(), $e);
	    }

        return $values;
    }

    /**
     * @param string $key
     * @param int $howMuch
     *
     * @return int
     */
    public function increment(string $key, int $howMuch = 1): int
    {
        $this->checkKey($key);
        return $howMuch;
    }

    /**
     * @param string $key
     * @param int $howMuch
     *
     * @return int
     */
    public function decrement(string $key, int $howMuch = 1): int
    {
        $this->checkKey($key);
        return $howMuch;
    }

}
