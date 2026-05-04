<?php declare(strict_types=1);

namespace Koldy;

use Closure;
use Koldy\Cache\Adapter\AbstractCacheAdapter;
use Koldy\Cache\FailoverProxy;
use Koldy\Config\Exception as ConfigException;
use Throwable;

/**
 * The cache class.
 *
 * @link http://koldy.net/docs/cache
 */
class Cache
{

	/**
	 * The initialized adapters. Entries are either bare adapters or
	 * FailoverProxy instances (when the corresponding config has on_fail).
	 *
	 * @var AbstractCacheAdapter[]
	 */
	protected static array $adapters = [];

	/**
	 * Per-request registry of cache adapters that have been observed failing
	 * with a CacheConnectionException. Each entry is keyed by the adapter's
	 * config name and holds the timestamp at which the mark expires plus the
	 * exception that caused it. Managed by markFailed/isFailed/clearFailed.
	 *
	 * @var array<string, array{at: int, until: int, cause: Throwable|null}>
	 */
	protected static array $failures = [];

	/**
	 * Get multiple keys from default cache engine
	 *
	 * @param array $keys
	 *
	 * @return array
	 * @throws Exception
	 * @link http://koldy.net/docs/cache#get-multi
	 */
	public static function getMulti(array $keys): array
	{
		return static::getAdapter()->getMulti($keys);
	}

	/**
	 * Get the cache adapter for the given config key (or the first configured
	 * adapter if none is given). If the resolved config declares an "on_fail"
	 * block, the returned adapter is wrapped in a {@see FailoverProxy} so
	 * that connection-level failures fall over to the configured next
	 * adapter. The proxy is cached just like any bare adapter, so subsequent
	 * lookups in the same request return the same instance.
	 *
	 * @param string|null $adapter
	 *
	 * @return AbstractCacheAdapter
	 * @throws Exception
	 */
	public static function getAdapter(string|null $adapter = null): AbstractCacheAdapter
	{
		$key = $adapter ?? static::getConfig()->getFirstKey();

		if (isset(static::$adapters[$key])) {
			return static::$adapters[$key];
		}

		$bare = static::buildAdapter($key);
		$configArray = static::getConfig()->get($key) ?? [];

		// Disabled adapters resolve to DevNull, which has no underlying
		// connection and therefore no notion of failure — wrapping it in a
		// failover proxy would be meaningless.
		if (isset($configArray['on_fail']) && !($bare instanceof Cache\Adapter\DevNull)) {
			static::$adapters[$key] = static::wrapInFailoverProxy($key, $bare, $configArray['on_fail']);
		} else {
			static::$adapters[$key] = $bare;
		}

		return static::$adapters[$key];
	}

	/**
	 * Resolve the bare adapter (without any failover proxy) for the given
	 * config key. Useful when callers need adapter-specific functionality not
	 * exposed on AbstractCacheAdapter — e.g. {@see Cache\Adapter\Memcached::getInstance()}
	 * for direct access to the native \Memcached client. For everything else,
	 * prefer {@see getAdapter()} so failover applies.
	 *
	 * @param string|null $adapter
	 *
	 * @return AbstractCacheAdapter
	 * @throws Exception
	 */
	public static function getRawAdapter(string|null $adapter = null): AbstractCacheAdapter
	{
		$key = $adapter ?? static::getConfig()->getFirstKey();
		$resolved = static::getAdapter($key);

		return $resolved instanceof FailoverProxy ? $resolved->getPrimary() : $resolved;
	}

	/**
	 * Instantiate the adapter declared by the given cache config key, without
	 * applying any failover wrapping. Honours `enabled`, `module`, and
	 * `adapter_class` keys.
	 *
	 * @param string $key
	 *
	 * @return AbstractCacheAdapter
	 * @throws Exception
	 */
	protected static function buildAdapter(string $key): AbstractCacheAdapter
	{
		$configArray = static::getConfig()->get($key) ?? [];

		if (($configArray['enabled'] ?? false) === false) {
			return new Cache\Adapter\DevNull([]);
		}

		if (isset($configArray['module'])) {
			Application::registerModule($configArray['module']);
		}

		$className = $configArray['adapter_class'] ?? null;

		if ($className === null) {
			throw new ConfigException("Cache config under key={$key} doesn't have defined 'adapter_class'; please set the 'adapter_class' with the name of class that extends \\Koldy\\Cache\\Adapter\\AbstractCacheAdapter");
		}

		if (!class_exists($className, true)) {
			throw new ConfigException("Class={$className} defined in cache config under key={$key} wasn't found; check the name and namespace of class and if class can be loaded");
		}

		return new $className($configArray['options'] ?? []);
	}

	/**
	 * Validate the on_fail block from a cache config and build the proxy.
	 * Validation is eager so misconfiguration surfaces at adapter resolution
	 * time, not only when the primary first fails. The next adapter itself is
	 * resolved lazily by the proxy when failover actually triggers, so an
	 * unused fallback never opens a connection.
	 *
	 * @param string $primaryName
	 * @param AbstractCacheAdapter $primary
	 * @param mixed $onFail
	 *
	 * @return FailoverProxy
	 * @throws ConfigException
	 */
	protected static function wrapInFailoverProxy(string $primaryName, AbstractCacheAdapter $primary, mixed $onFail): FailoverProxy
	{
		if (!is_array($onFail)) {
			throw new ConfigException("Cache config under key={$primaryName} has on_fail that is not an array");
		}

		$nextName = $onFail['next_adapter'] ?? null;
		if (!is_string($nextName) || $nextName === '') {
			throw new ConfigException("Cache config under key={$primaryName} has on_fail without a valid 'next_adapter' (must be a non-empty string)");
		}

		if ($nextName === $primaryName) {
			throw new ConfigException("Cache config under key={$primaryName} declares itself as its own on_fail.next_adapter");
		}

		$config = static::getConfig();
		if ($config->get($nextName) === null) {
			throw new ConfigException("Cache config under key={$primaryName} declares on_fail.next_adapter='{$nextName}' but no such cache adapter is configured");
		}

		$ttl = $onFail['ttl'] ?? null;
		if (!is_int($ttl) || $ttl < 1) {
			throw new ConfigException("Cache config under key={$primaryName} has on_fail without a valid 'ttl' (must be an integer >= 1, in seconds)");
		}

		return new FailoverProxy($primaryName, $primary, $nextName, $ttl);
	}

	/**
	 * Mark the named cache adapter as failed for the next $ttl seconds. While
	 * an adapter is marked failed, calls routed through its FailoverProxy go
	 * directly to the next adapter in the chain, skipping a fresh attempt at
	 * the primary. The mark auto-clears once the TTL has elapsed.
	 *
	 * Called by FailoverProxy when the primary throws CacheConnectionException;
	 * applications may also call it directly when they have out-of-band
	 * evidence that an adapter is unhealthy.
	 *
	 * @param string $adapter
	 * @param int $ttl
	 * @param Throwable|null $cause
	 */
	public static function markFailed(string $adapter, int $ttl, ?Throwable $cause = null): void
	{
		$now = time();
		static::$failures[$adapter] = [
			'at' => $now,
			'until' => $now + $ttl,
			'cause' => $cause
		];
	}

	/**
	 * Whether the named cache adapter is currently marked failed. Auto-clears
	 * the mark when its TTL has elapsed, so the next call retries the primary.
	 *
	 * @param string $adapter
	 *
	 * @return bool
	 */
	public static function isFailed(string $adapter): bool
	{
		if (!isset(static::$failures[$adapter])) {
			return false;
		}

		if (time() >= static::$failures[$adapter]['until']) {
			unset(static::$failures[$adapter]);
			return false;
		}

		return true;
	}

	/**
	 * Clear any failure mark on the named adapter. Useful in tests, or when
	 * the caller has externally verified that the adapter is healthy and
	 * wants to skip the remaining TTL.
	 *
	 * @param string $adapter
	 */
	public static function clearFailed(string $adapter): void
	{
		unset(static::$failures[$adapter]);
	}

	/**
	 * Get cache config
	 *
	 * @return Config
	 * @throws Exception
	 */
	public static function getConfig(): Config
	{
		return Application::getConfig('cache', true);
	}

	/**
	 * Get the key from default cache engine
	 *
	 * @param string $key
	 *
	 * @return mixed
	 * @throws Exception
	 * @link http://koldy.net/docs/cache#get
	 */
	public static function get(string $key): mixed
	{
		return static::getAdapter()->get($key);
	}

	/**
	 * Set the value to default cache engine and overwrite if keys already exists
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param int|null $seconds [optional]
	 *
	 * @throws Exception
	 * @link http://koldy.net/docs/cache#set
	 */
	public static function set(string $key, mixed $value, int|null $seconds = null): void
	{
		static::getAdapter()->set($key, $value, $seconds);
	}

	/**
	 * Set multiple values to default cache engine and overwrite if keys already exists
	 *
	 * @param array $keyValuePairs
	 * @param int|null $seconds [optional]
	 *
	 * @throws Exception
	 * @link http://koldy.net/docs/cache#set-multi
	 */
	public static function setMulti(array $keyValuePairs, int|null $seconds = null): void
	{
		static::getAdapter()->setMulti($keyValuePairs, $seconds);
	}

	/**
	 * Is there a key under default cache
	 *
	 * @param string $key
	 *
	 * @return boolean
	 * @throws Exception
	 * @link http://koldy.net/docs/cache#has
	 */
	public static function has(string $key): bool
	{
		return static::getAdapter()->has($key);
	}

	/**
	 * Delete the key from cache
	 *
	 * @param string $key
	 *
	 * @throws Exception
	 * @link http://koldy.net/docs/cache#delete
	 */
	public static function delete(string $key): void
	{
		static::getAdapter()->delete($key);
	}

	/**
	 * Delete multiple keys from cache
	 *
	 * @param array $keys
	 *
	 * @throws Exception
	 * @link http://koldy.net/docs/cache#delete-multi
	 */
	public static function deleteMulti(array $keys): void
	{
		static::getAdapter()->deleteMulti($keys);
	}

	/**
	 * Get or set the key's value
	 *
	 * @param string $key
	 * @param Closure $functionOnSet
	 * @param int|null $seconds
	 *
	 * @return mixed
	 * @throws Cache\Exception
	 * @throws Exception
	 * @link http://koldy.net/docs/cache#get-or-set
	 */
	public static function getOrSet(string $key, Closure $functionOnSet, int|null $seconds = null): mixed
	{
		return static::getAdapter()->getOrSet($key, $functionOnSet, $seconds);
	}

	/**
	 * Increment value in cache
	 *
	 * @param string $key
	 * @param int $howMuch
	 *
	 * @return int
	 *
	 * @throws Exception
	 * @link http://koldy.net/docs/cache#increment-decrement
	 */
	public static function increment(string $key, int $howMuch = 1): int
	{
		return static::getAdapter()->increment($key, $howMuch);
	}

	/**
	 * Decrement value in cache
	 *
	 * @param string $key
	 * @param int $howMuch
	 *
	 * @return int
	 *
	 * @throws Exception
	 * @link http://koldy.net/docs/cache#increment-decrement
	 */
	public static function decrement(string $key, int $howMuch = 1): int
	{
		return static::getAdapter()->decrement($key, $howMuch);
	}

	/**
	 * Does requested Adapter exists (this will also return true if Adapter is disabled)
	 *
	 * @param string $key
	 *
	 * @return boolean
	 * @link http://koldy.net/docs/cache#engines
	 */
	public static function hasAdapter(string $key): bool
	{
		return isset(static::$adapters[$key]);
	}

	/**
	 * Is given cache Adapter enabled or not? If Adapter is instance of
	 * DevNull, it will also return false so be careful about that
	 *
	 * @param string|null $adapter
	 *
	 * @return boolean
	 * @throws Exception
	 */
	public static function isEnabled(string|null $adapter = null): bool
	{
		return !(static::getAdapter($adapter) instanceof Cache\Adapter\DevNull);
	}

}
