<?php declare(strict_types=1);

namespace Koldy\Cache\Adapter;

use Closure;
use Exception;
use Koldy\Adapter\Memcached\ResultCodeInfo;
use Koldy\Config\Exception as ConfigException;
use Memcached as NativeMemcached;
use Koldy\Cache\Exception as CacheException;
use Throwable;

/**
 * The Memcached adapter defined in Koldy is using Memcached and not Memcache class. Notice the difference with "d" letter.
 * In order the use this adapter, your PHP installation must have Memcached extension available. To be sure, run your
 * phpinfo() and check if Memcache adapter is mentioned.
 *
 * @link https://koldy.net/framework/docs/2.0/cache/memcached.md
 */
class Memcached extends AbstractCacheAdapter
{

    /**
     * If you need access to native instance, you may use this public property
     *
     * @var NativeMemcached|null
     */
    public NativeMemcached | null $memcached = null;

    /**
     * Get the instance of \Memcached
     *
     * @return NativeMemcached
     * @throws ConfigException
     */
    protected function getInstance(): NativeMemcached
    {
        if ($this->memcached === null) {
            // first check if servers were defined
            if (!isset($this->config['servers'])) {
                throw new ConfigException('There are no defined Memcached servers in configuration; check if \'servers\' key exists in cache configuration file');
            }

            $serversCount = count($this->config['servers']);

            if ($serversCount == 0) {
                throw new ConfigException('There are no defined Memcached servers in configuration; check the \'servers\' key');
            }

            $this->memcached = isset($this->config['persistent_id']) ? new NativeMemcached($this->config['persistent_id']) : new NativeMemcached();

            $adapterOptions = [
              NativeMemcached::OPT_LIBKETAMA_COMPATIBLE => true // recommended on https://www.php.net/manual/en/memcached.constants.php
            ];

            if (isset($this->config['adapter_options']) && is_array($this->config['adapter_options']) && count($this->config['adapter_options']) > 0) {
                $adapterOptions = array_merge($adapterOptions, $this->config['adapter_options']);
            }

            $this->memcached->setOptions($adapterOptions);

            /**
             * The following IF will prevent opening new connections on every new class instance
             *
             * @link https://www.php.net/manual/en/memcached.construct.php#93536
             */
            if (count($this->memcached->getServerList()) < $serversCount) {
                $this->memcached->addServers($this->config['servers']);
            }
        }

        return $this->memcached;
    }

    /**
     * Get the key name for the storage into memcached
     *
     * @param string $key
     *
     * @return string
     */
    protected function getKeyName(string $key): string
    {
        return ($this->config['prefix'] ?? '') . $key;
    }

	/**
	 * Get the value from cache by given key
	 *
	 * @param string $key
	 *
	 * @return mixed value or null if key doesn't exists or cache is disabled
	 * @throws CacheException
	 * @throws ConfigException
	 * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
	 */
    public function get(string $key): mixed
    {
        $key = $this->getKeyName($key);
        $value = $this->getInstance()->get($key);

		$code = $this->getInstance()->getResultCode();

		if ($code === NativeMemcached::RES_SUCCESS) {
			return $value;
		}

		if ($code === NativeMemcached::RES_NOTFOUND) {
			return null;
		}

	    $info = new ResultCodeInfo($code);
		// otherwise, we're dealing with unknown error here, so we should throw an exception
        throw new CacheException("Memcached error #{$code}: {$info->getDescription()}");
    }

    /**
     * Get the array of values from cache by given keys
     *
     * @param array $keys
     *
     * @return array
     * @throws CacheException
     * @throws ConfigException
     * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
     */
    public function getMulti(array $keys): array
    {
        if (count($keys) == 0) {
            throw new CacheException('Can not use getMulti with empty set of keys');
        }

        $keys = array_values($keys);
        $result = [];

        $serverKeys = [];
        foreach ($keys as $key) {
            $serverKeys[] = $this->getKeyName($key);
        }

        if (($serverValues = $this->getInstance()->getMulti($serverKeys)) === false) {
	        $info = new ResultCodeInfo($this->getInstance()->getResultCode());
	        throw new CacheException("Memcached error #{$info->getResultCode()}: getting multiple keys from cache failed with a reason: {$info->getDescription()}");
        }

        foreach ($keys as $key) {
            $serverKey = $this->getKeyName($key);
            $result[$key] = $serverValues[$serverKey] ?? null;
        }

        return $result;
    }

	/**
	 * Set the value to cache identified by key
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param int|null $seconds [optional] if not set, default is used
	 *
	 * @throws ConfigException
	 * @throws CacheException
	 * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
	 */
    public function set(string $key, mixed $value, int $seconds = null): void
    {
        $key = $this->getKeyName($key);

		if ($this->getInstance()->set($key, $value, ($seconds ?? $this->defaultDuration)) === false) {
			$info = new ResultCodeInfo($this->getInstance()->getResultCode());
			throw new CacheException("Memcached error #{$info->getResultCode()}: storing key [{$key}] failed with a reason: {$info->getDescription()}");
		}
    }

	/**
	 * Set multiple values to default cache engine and overwrite if keys already exists
	 *
	 * @param array $keyValuePairs
	 * @param int|null $seconds [optional] if not set, default is used
	 *
	 * @throws CacheException
	 * @throws ConfigException
	 * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
	 */
    public function setMulti(array $keyValuePairs, int $seconds = null): void
    {
        if (count($keyValuePairs) == 0) {
            throw new CacheException('Can not use setMulti on empty array');
        }

        $serverKeyValuePairs = [];

        foreach ($keyValuePairs as $key => $value) {
            $serverKeyValuePairs[$this->getKeyName($key)] = $value;
        }

        if ($this->getInstance()->setMulti($serverKeyValuePairs, ($seconds ?? $this->defaultDuration)) === false) {
	        $info = new ResultCodeInfo($this->getInstance()->getResultCode());
	        throw new CacheException("Memcached error #{$info->getResultCode()}: storing multiple keys failed with a reason: {$info->getDescription()}");
        }
    }

    /**
     * @param array $keys
     * @param Closure $functionOnMissingKeys
     * @param int|null $seconds
     *
     * @return array
     * @throws CacheException
     * @throws ConfigException
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
	        try {
		        $setValues = call_user_func($functionOnMissingKeys, $found, $missing, $seconds);
	        } catch (Exception | Throwable $e) {
		        throw new CacheException("Unable to cache set of values because exception was thrown in setter function on missing keys: {$e->getMessage()}", $e->getCode(), $e);
	        }

            if (!is_array($setValues)) {
                throw new CacheException('Return value from function passed to getOrSetMulti must return array; got ' . gettype($setValues));
            }

            $return = array_merge($return, $setValues);
        }

        return $return;
    }

    /**
     * Check if item under key name exists. It will return false if item expired.
     *
     * @param string $key
     *
     * @return boolean
     * @throws ConfigException
     * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
     */
    public function has(string $key): bool
    {
        $key = $this->getKeyName($key);
        return !($this->getInstance()->get($key) === false);
    }

	/**
	 * Delete the item from cache
	 *
	 * @param string $key
	 *
	 * @throws CacheException
	 * @throws ConfigException
	 * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
	 */
    public function delete(string $key): void
    {
        $key = $this->getKeyName($key);

		if ($this->getInstance()->delete($key) !== true) {
			$code = $this->getInstance()->getResultCode();

			if (!($code === NativeMemcached::RES_SUCCESS || $code === NativeMemcached::RES_NOTFOUND)) {
				$info = new ResultCodeInfo($code);
				// otherwise, we're dealing with unknown error here, so we should throw an exception
				throw new CacheException("Couldn't delete cache key \"{$key}\" because Memcached returned #{$code}: {$info->getDescription()}");
			}
		}
    }

    /**
     * Delete multiple items from cache engine
     *
     * @param array $keys
     *
     * @throws ConfigException
     * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
     */
    public function deleteMulti(array $keys): void
    {
        $serverKeys = [];
        foreach ($keys as $key) {
            $serverKeys[] = $this->getKeyName($key);
        }

        $this->getInstance()->deleteMulti($serverKeys);
    }

	/**
	 * Delete all cached items
	 * @throws CacheException
	 * @throws ConfigException
	 */
    public function deleteAll(): void
    {
		if ($this->getInstance()->flush() === false) {
			$code = $this->getInstance()->getResultCode();
			$info = new ResultCodeInfo($code);
			// otherwise, we're dealing with unknown error here, so we should throw an exception
			throw new CacheException("Couldn't delete all cache keys because Memcached returned #{$code}: {$info->getDescription()}");
		}
    }

	/**
	 * Delete all cache items older then ...
	 *
	 * @param int|null $olderThanSeconds [optional] if not set, then default duration is used
	 */
    public function deleteOld(int $olderThanSeconds = null): void
    {
        // Note1: won't be implemented - you might potentially have a lot of keys stored and you really don't want to
        // accidentally iterate through it
        // Note2: Memcache automatically invalidates old keys, so you don't have to do it manually
    }

    /**
     * Increment number value in cache. This will not work if item expired!
     *
     * @param string $key
     * @param int $howMuch [optional] default 1
     *
     * @return int
     * @throws ConfigException
     * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
     */
    public function increment(string $key, int $howMuch = 1): int
    {
        $key = $this->getKeyName($key);
        return $this->getInstance()->increment($key, $howMuch);
    }

    /**
     * Decrement number value in cache. This will not work if item expired!
     *
     * @param string $key
     * @param int $howMuch [optional] default 1
     *
     * @return int
     * @throws ConfigException
     * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
     */
    public function decrement(string $key, int $howMuch = 1): int
    {
        $key = $this->getKeyName($key);
        return $this->getInstance()->decrement($key, $howMuch);
    }
}
