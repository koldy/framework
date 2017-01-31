<?php declare(strict_types = 1);

namespace Koldy\Cache\Adapter;

/**
 * If you don't want to use your cache driver, you can redirect all cache data into black whole! Learn more at http://en.wikipedia.org/wiki//dev/null
 *
 * This class handles the cache driver instance, but using it, nothing will happen. This class will be initialized if you try to use driver that is disabled.
 *
 * @link http://koldy.net/docs/cache/devnull
 */
class DevNull extends AbstractCacheAdapter
{

    /**
     * @param string $key
     *
     * @return mixed|null
     */
    public function get(string $key)
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
     * @link http://koldy.net/docs/cache#get-multi
     */
    public function getMulti(array $keys): array
    {
        foreach (array_values($keys) as $key) {
            $this->checkKey($key);
        }

        return [];
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $seconds
     */
    public function set(string $key, $value, int $seconds = null): void
    {
        $this->checkKey($key);
    }

    /**
     * Set multiple values to default cache engine and overwrite if keys already exists
     *
     * @param array $keyValuePairs
     * @param int|null $seconds
     *
     * @link http://koldy.net/docs/cache#set-multi
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
     * @link http://koldy.net/docs/cache#delete-multi
     */
    public function deleteMulti(array $keys): void
    {
        foreach (array_values($keys) as $key) {
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
     * @param int $olderThen
     */
    public function deleteOld(int $olderThen = null): void
    {
        // nothing to delete
    }

    /**
     * @param string $key
     * @param \Closure $functionOnSet
     * @param int $seconds
     *
     * @return mixed
     */
    public function getOrSet(string $key, \Closure $functionOnSet, int $seconds = null)
    {
        $this->checkKey($key);
        return call_user_func($functionOnSet, $key, $seconds);
    }

    /**
     * @param array $keys
     * @param \Closure $functionOnMissingKeys
     * @param int|null $seconds
     *
     * @return array
     */
    public function getOrSetMulti(array $keys, \Closure $functionOnMissingKeys, int $seconds = null): array
    {
        foreach (array_values($keys) as $key) {
            $this->checkKey($key);
        }

        return call_user_func($functionOnMissingKeys, $keys, $keys, $seconds); // calling function, red keys, missing keys, seconds
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
