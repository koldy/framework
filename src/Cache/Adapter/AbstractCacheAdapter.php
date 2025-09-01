<?php declare(strict_types=1);

namespace Koldy\Cache\Adapter;

use Closure;
use InvalidArgumentException;
use Koldy\Cache\Exception as CacheException;
use Koldy\Log;

/**
 * Abstract class for making any kind of new cache adapter. If you want to create your own cache adapter, then extend
 * this class.
 *
 * @link https://koldy.net/framework/docs/2.0/cache.md#creating-custom-cache-storage-adapter
 */
abstract class AbstractCacheAdapter
{

	/**
	 * Array of loaded config (the options part in config)
	 *
	 * @var array
	 */
	protected array $config;

	/**
	 * Default duration of cache - if user doesn't pass it to set/add methods
	 *
	 * @var int
	 */
	protected int $defaultDuration = 3600;

	/**
	 * Construct the object by array of config properties. Config keys are set
	 * in config/cache.php and this array will contain only block for the
	 * requested cache adapter. Yes, you can also build this manually, but that
	 * is not recommended.
	 *
	 * @param array $config
	 */
	public function __construct(array $config)
	{
		$this->config = $config;

		if (isset($config['default_duration'])) {
			$this->defaultDuration = $config['default_duration'];
		}

		if (isset($config['clean_old']) && $config['clean_old'] === true) {
			$self = $this;
			register_shutdown_function(function () use ($self) {
				$self->deleteOld();
			});
		}
	}

	/**
	 * Delete all cache items older then ...
	 *
	 * @param int|null $olderThanSeconds [optional] if not set, then default duration is used
	 */
	abstract public function deleteOld(int|null $olderThanSeconds = null): void;

	/**
	 * Get the array of values from cache by given keys
	 *
	 * @param array $keys
	 *
	 * @return array value or null if key doesn't exists or cache is disabled
	 * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
	 */
	abstract public function getMulti(array $keys): array;

	/**
	 * Set multiple values to default cache engine and overwrite if keys already exists
	 *
	 * @param array $keyValuePairs
	 * @param int|null $seconds
	 *
	 * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
	 */
	abstract public function setMulti(array $keyValuePairs, int|null $seconds = null): void;

	/**
	 * @param array $keys
	 * @param Closure $functionOnMissingKeys
	 * @param int|null $seconds
	 *
	 * @return array
	 */
	abstract public function getOrSetMulti(
		array $keys,
		Closure $functionOnMissingKeys,
		int|null $seconds = null
	): array;

	/**
	 * Set the value under key and remember it forever! Okay, "forever" has its
	 * own duration and that is 50 years. So, is 50 years enough for you?
	 *
	 * @param string $key
	 * @param mixed $value
	 *
	 * @throws CacheException
	 */
	public function setForever(string $key, mixed $value): void
	{
		$this->checkKey($key);
		$this->set($key, $value, time() + 3600 * 24 * 365 * 50);
	}

	/**
	 * Validate key name and throw exception if something is wrong
	 *
	 * @param string $key
	 *
	 * @throws InvalidArgumentException
	 */
	protected function checkKey(string $key): void
	{
		// the max length is 255-32-1 = 222
		if (strlen($key) == 0) {
			throw new InvalidArgumentException('Cache key name can\'t be empty string');
		}

		if (strlen($key) > 222) {
			throw new InvalidArgumentException('Cache key name mustn\'t be longer then 222 characters');
		}
	}

	/**
	 * Set the value to cache identified by key
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param int|null $seconds [optional] if not set, default is used
	 *
	 * @throws CacheException
	 * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
	 *
	 */
	abstract public function set(string $key, mixed $value, int|null $seconds = null): void;

	/**
	 * Check if item under key name exists. It will return false if item expired.
	 *
	 * @param string $key
	 *
	 * @return boolean
	 * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
	 */
	abstract public function has(string $key): bool;

	/**
	 * Deletes the item from cache engine
	 *
	 * @param string $key
	 *
	 * @throws CacheException
	 *
	 * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
	 */
	abstract public function delete(string $key): void;

	/**
	 * Delete multiple items from cache engine
	 *
	 * @param array $keys
	 *
	 * @throws CacheException
	 *
	 * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
	 */
	abstract public function deleteMulti(array $keys): void;

	/**
	 * Delete all cached items
	 *
	 * @throws CacheException
	 */
	abstract public function deleteAll(): void;

	/**
	 * Get the value from cache if exists, otherwise, set the value returned
	 * from the function you pass. The function may contain more steps, such as
	 * fetching data from database or etc.
	 *
	 * @param string $key
	 * @param Closure $functionOnSet
	 * @param int|null $seconds
	 *
	 * @return mixed
	 *
	 * @throws CacheException
	 * @example
	 * Cache::getOrSet('key', function() {
	 *    return "the value";
	 * });
	 */
	public function getOrSet(string $key, Closure $functionOnSet, int|null $seconds = null): mixed
	{
		$this->checkKey($key);

		try {
			if (($value = $this->get($key)) !== null) {
				return $value;
			}
		} catch (CacheException) {
			// if we caught an exception here, then we'll just ignore it and we'll act like we couldn't read a cache, so we'll say: nah, let's get the new value and let's try to store the value
		}

		//	    try {
		$value = call_user_func($functionOnSet);
		// ^^ we will let the eventual exception to pass through so it can be caught by the caller
		//	    } catch (Exception | Throwable $e) {
		// ^^ this is serious; a user callback function couldn't get the value we need - this is not cache exception and we should throw it
		//		    throw new CacheException("Unable to cache value with key={$key} because of exception thrown in setter function: {$e->getMessage()}", $e->getCode(), $e);
		//	    }

		try {
			$this->set($key, $value, $seconds);
		} catch (CacheException $e) {
			// ok, if we couldn't write a cache value to cache storage, then it might be something wrong with a cache storage
			// in this case, let's log the problem, but we'll return a value back so the system continues to work - it'll probably be slow/slower
			// but it's better than not working at all
			Log::warning("Couldn't store value(s) to cache key \"{$key}\" so it's possible that system will continue to use non-cached value on next use.",
				$e);
		}

		return $value;
	}

	/**
	 * Get the value from cache by given key
	 *
	 * @param string $key
	 *
	 * @return mixed value or null if key doesn't exists or cache is disabled
	 * @throws CacheException
	 * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
	 *
	 */
	abstract public function get(string $key): mixed;

	/**
	 * Increment number value in cache. This will not work if item expired!
	 *
	 * @param string $key
	 * @param int $howMuch [optional] default 1
	 *
	 * @return int
	 * @throws CacheException
	 * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
	 */
	public function increment(string $key, int $howMuch = 1): int
	{
		$this->checkKey($key);

		$data = $this->get($key);
		if ($data !== null) {
			$data += $howMuch;
			$this->set($key, $data);
			return $data;
		} else {
			$this->set($key, 1);
			return 1;
		}
	}

	/**
	 * Decrement number value in cache. This will not work if item expired!
	 *
	 * @param string $key
	 * @param int $howMuch
	 *
	 * @return int
	 * @throws CacheException
	 * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
	 */
	public function decrement(string $key, int $howMuch = 1): int
	{
		$this->checkKey($key);

		$data = $this->get($key);
		if ($data !== null) {
			$data -= $howMuch;
			$this->set($key, $data);
			return $data;
		} else {
			$this->set($key, -1);
			return -1;
		}
	}

}
