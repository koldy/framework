<?php declare(strict_types=1);

namespace Koldy;

use Closure;
use Koldy\Cache\Adapter\AbstractCacheAdapter;
use Koldy\Config\Exception as ConfigException;

/**
 * The cache class.
 *
 * @link http://koldy.net/docs/cache
 */
class Cache
{

    /**
     * The initialized adapters
     *
     * @var AbstractCacheAdapter[]
     */
    protected static array $adapters = [];

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
     * Get the cache adapter
     *
     * @param string|null $adapter
     *
     * @return \Koldy\Cache\Adapter\AbstractCacheAdapter
     * @throws \Koldy\Exception
     */
    public static function getAdapter(string $adapter = null): AbstractCacheAdapter
    {
        $key = $adapter ?? static::getConfig()->getFirstKey();

        if (isset(static::$adapters[$key])) {
            return static::$adapters[$key];
        }

        $config = static::getConfig();
        $configArray = $config->get($key) ?? [];

        if (($configArray['enabled'] ?? false) === false) {
            static::$adapters[$key] = new Cache\Adapter\DevNull([]);
        } else {

            if (isset($configArray['module'])) {
                Application::registerModule($configArray['module']);
            }

            $className = $configArray['adapter_class'] ?? null;

            if ($className === null) {
                throw new ConfigException("Cache config under key={$key} doesn\'t have defined 'adapter_class'; please set the 'adapter_class' with the name of class that extends \\Koldy\\Cache\\Adapter\\AbstractCacheAdapter");
            }

            if (!class_exists($className, true)) {
                throw new ConfigException("Class={$className} defined in cache config under key={$adapter} wasn't found; check the name and namespace of class and if class can be loaded");
            }

            static::$adapters[$key] = new $className($configArray['options'] ?? []);
        }

        return static::$adapters[$key];
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
     * Set the value to default cache engine and overwrite if keys already exists
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $seconds [optional]
     *
     * @throws Exception
     * @link http://koldy.net/docs/cache#set
     */
    public static function set(string $key, mixed $value, int $seconds = null): void
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
    public static function setMulti(array $keyValuePairs, int $seconds = null): void
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
    public static function getOrSet(string $key, Closure $functionOnSet, int $seconds = null): mixed
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
    public static function isEnabled(string $adapter = null): bool
    {
        return !(static::getAdapter($adapter) instanceof Cache\Adapter\DevNull);
    }

}
