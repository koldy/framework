<?php declare(strict_types = 1);

namespace Koldy\Cache\Adapter;

/**
 * Abstract class for making any kind of new cache adapter. If you want to create your own cache adapter, then extend this class.
 *
 * @link http://koldy.net/docs/cache#custom
 */
abstract class AbstractCacheAdapter
{

    /**
     * Array of loaded config (the options part in config)
     *
     * @var array
     */
    protected $config;

    /**
     * Default duration of cache - if user doesn't pass it to set/add methods
     *
     * @var int
     */
    protected $defaultDuration = 3600;

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
     * Validate key name and throw exception if something is wrong
     *
     * @param string $key
     *
     * @throws \InvalidArgumentException
     */
    protected function checkKey(string $key)
    {
        // the max length is 255-32-1 = 222
        if (strlen($key) == 0) {
            throw new \InvalidArgumentException('Cache key name can\'t be empty string');
        }

        if (strlen($key) > 222) {
            throw new \InvalidArgumentException('Cache key name mustn\'t be longer then 222 characters');
        }
    }

    /**
     * Get the value from cache by given key
     *
     * @param string $key
     *
     * @return mixed value or null if key doesn't exists or cache is disabled
     * @link http://koldy.net/docs/cache#get
     */
    abstract public function get(string $key);

    /**
     * Get the array of values from cache by given keys
     *
     * @param array $keys
     *
     * @return mixed value or null if key doesn't exists or cache is disabled
     * @link http://koldy.net/docs/cache#get-multi
     */
    abstract public function getMulti(array $keys): array;

    /**
     * Set the value to cache identified by key
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $seconds [optional] if not set, default is used
     *
     * @link http://koldy.net/docs/cache#set
     */
    abstract public function set(string $key, $value, int $seconds = null): void;

    /**
     * Set multiple values to default cache engine and overwrite if keys already exists
     *
     * @param array $keyValuePairs
     * @param int|null $seconds
     *
     * @link http://koldy.net/docs/cache#set-multi
     */
    abstract public function setMulti(array $keyValuePairs, int $seconds = null): void;

    /**
     * @param array $keys
     * @param \Closure $functionOnMissingKeys
     * @param int|null $seconds
     *
     * @return array
     */
    abstract public function getOrSetMulti(array $keys, \Closure $functionOnMissingKeys, int $seconds = null): array;

    /**
     * Set the value under key and remember it forever! Okay, "forever" has its
     * own duration and that is 15 years. So, is 15 years enough for you?
     *
     * @param string $key
     * @param mixed $value
     */
    public function setForever(string $key, $value): void
    {
        $this->checkKey($key);
        $this->set($key, $value, time() + 3600 * 24 * 365 * 15);
    }

    /**
     * Check if item under key name exists. It will return false if item expired.
     *
     * @param string $key
     *
     * @return boolean
     * @link http://koldy.net/docs/cache#has
     */
    abstract public function has(string $key): bool;

    /**
     * Deletes the item from cache engine
     *
     * @param string $key
     *
     * @link http://koldy.net/docs/cache#delete
     */
    abstract public function delete(string $key): void;

    /**
     * Delete multiple items from cache engine
     *
     * @param array $keys
     *
     * @link http://koldy.net/docs/cache#delete-multi
     */
    abstract public function deleteMulti(array $keys): void;

    /**
     * Delete all cached items
     */
    abstract public function deleteAll(): void;

    /**
     * Delete all cache items older then ...
     *
     * @param int $olderThen [optional] if not set, then default duration is used
     */
    abstract public function deleteOld(int $olderThen = null): void;

    /**
     * Get the value from cache if exists, otherwise, set the value returned
     * from the function you pass. The function may contain more steps, such as
     * fetching data from database or etc.
     *
     * @param string $key
     * @param \Closure $functionOnSet
     * @param int|null $seconds
     *
     * @return mixed
     *
     * @example
     * Cache::getOrSet('key', function() {
     *    return "the value";
     * });
     */
    public function getOrSet(string $key, \Closure $functionOnSet, int $seconds = null)
    {
        $this->checkKey($key);

        if (($value = $this->get($key)) !== null) {
            return $value;
        } else {
            $value = call_user_func($functionOnSet);
            $this->set($key, $value, $seconds);
            return $value;
        }
    }

    /**
     * Increment number value in cache. This will not work if item expired!
     *
     * @param string $key
     * @param int $howMuch [optional] default 1
     *
     * @return int
     * @link http://koldy.net/docs/cache#increment-decrement
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
     * @link http://koldy.net/docs/cache#increment-decrement
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

    /**
     * Gets native instance of the adapter on which we're working on. If we're working with Memcached, then you'll
     * get \Memcached class instance. If you're working with files, then you'll get null.
     *
     * @return mixed
     */
    abstract public function getNativeInstance();

}
