<?php declare(strict_types = 1);

namespace Koldy\Cache\Adapter;

/**
 * This cache adapter holds cached data only in request's scope (memory). As soon as request ends, everything will disappear.
 *
 * @link http://koldy.net/docs/cache/request
 */
class Request extends AbstractCacheAdapter
{

    /**
     * The array of loaded and/or data that will be stored
     *
     * @var array
     */
    private $data = [];

    /**
     * Get the value from the cache by key
     *
     * @param string $key
     *
     * @return mixed value or null if key doesn't exists or cache is disabled
     */
    public function get(string $key)
    {
        if ($this->has($key)) {
            return $this->data[$key]->data;
        }

        return null;
    }

    /**
     * Get the array of values from cache by given keys
     *
     * @param array $keys
     *
     * @return array
     * @link http://koldy.net/docs/cache#get-multi
     */
    public function getMulti(array $keys): array
    {
        $result = [];

        foreach (array_values($keys) as $key) {
            $result[$key] = $this->get($key);
        }

        return $result;
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
            $setValues = call_user_func($functionOnMissingKeys, $found, $missing, $seconds);
            $return = array_merge($return, $setValues);
        }

        return $return;
    }

    /**
     * Set the cache value by the key
     *
     * @param string $key
     * @param string $value
     * @param integer $seconds
     */
    public function set(string $key, $value, int $seconds = null): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Set multiple values to default cache engine and overwrite if keys already exists
     *
     * @param array $keyValuePairs
     * @param int $seconds [optional] if not set, default is used
     *
     * @link http://koldy.net/docs/cache#set-multi
     */
    public function setMulti(array $keyValuePairs, int $seconds = null): void
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

    /**
     * @param string $key
     */
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
     * @link http://koldy.net/docs/cache#delete-multi
     */
    public function deleteMulti(array $keys): void
    {
        foreach (array_values($keys) as $key) {
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

    /**
     * @param int $olderThenSeconds
     */
    public function deleteOld(int $olderThenSeconds = null): void
    {
        // nothing to do
    }

}
