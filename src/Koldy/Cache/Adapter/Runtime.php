<?php declare(strict_types=1);

namespace Koldy\Cache\Adapter;

use Closure;

/**
 * This cache adapter holds cached data only in request's scope (memory, or runtime). As soon as request/script ends, everything will
 * disappear. It's best use is in CLI scripts.
 *
 * @link https://koldy.net/framework/docs/2.0/cache/runtime.md
 */
class Runtime extends AbstractCacheAdapter
{

    /**
     * The array of loaded and/or data that will be stored
     *
     * @var array
     */
    private array $data = [];

    /**
     * Get the value from the cache by key
     *
     * @param string $key
     *
     * @return mixed value or null if key doesn't exists or cache is disabled
     */
    public function get(string $key): mixed
    {
        if ($this->has($key)) {
            return $this->data[$key];
        }

        return null;
    }

    /**
     * Get the array of values from cache by given keys
     *
     * @param array $keys
     *
     * @return array
     * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
     */
    public function getMulti(array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }

        return $result;
    }

	/**
	 * @param array $keys
	 * @param Closure $functionOnMissingKeys
	 * @param int|null $seconds
	 *
	 * @return array
	 */
    public function getOrSetMulti(array $keys, Closure $functionOnMissingKeys, int|null $seconds = null): array
    {
        $found = [];
        $missing = [];
        $return = [];

        foreach ($keys as $key) {
            $value = $this->get($key);

            if ($value === null) {
                $missing[] = $key;
                $return[$key] = null;
            } else {
                $found[] = $key;
                $return[$key] = $value->data;
            }
        }

        if (count($missing) > 0) {
//	        try {
		        $setValues = call_user_func($functionOnMissingKeys, $found, $missing, $seconds);
//	        } catch (Exception | Throwable $e) {
//		        throw new CacheException("Unable to cache set of values because exception was thrown in setter function on missing keys: {$e->getMessage()}", $e->getCode(), $e);
//	        }
            $return = array_merge($return, $setValues);
        }

        return $return;
    }

	/**
	 * Set the cache value by the key
	 *
	 * @param string $key
	 * @param string $value
	 * @param int|null $seconds
	 */
    public function set(string $key, $value, int|null $seconds = null): void
    {
        $this->data[$key] = $value;
        // TODO: Respect time limit
    }

	/**
	 * Set multiple values to default cache engine and overwrite if keys already exists
	 *
	 * @param array $keyValuePairs
	 * @param int|null $seconds [optional] if not set, default is used
	 *
	 * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
	 */
    public function setMulti(array $keyValuePairs, int|null $seconds = null): void
    {
        foreach ($keyValuePairs as $key => $value) {
            $this->set($key, $value, $seconds);
        }
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function delete(string $key): void
    {
        if ($this->has($key)) {
            unset($this->data[$key]);
        }
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
            $this->delete($key);
        }
    }

    /**
     * Delete all
     */
    public function deleteAll(): void
    {
        $this->data = [];
    }

    public function deleteOld(int|null $olderThanSeconds = null): void
    {
        // nothing to do
    }

}
