<?php declare(strict_types=1);

namespace Koldy\Cache\Adapter;

use Koldy\Application;
use Koldy\Filesystem\Directory;
use Koldy\Cache\Exception as CacheException;
use Koldy\Log;

/**
 * This cache adapter will store all of your data into files somewhere on the server's filesystem. Every stored key represents one file on filesystem.
 *
 * @link http://koldy.net/docs/cache/files
 */
class Files extends AbstractCacheAdapter
{

    /**
     * The path to the folder where cache files will be stored
     *
     * @var string
     */
    protected $path = null;

    /**
     * The array of loaded and/or data that will be stored
     *
     * @var array
     */
    protected $data = [];

    /**
     * Flag if shutdown function is registered or not
     *
     * @var boolean
     */
    protected $shutdown = false;

    /**
     * Construct the object by array of config properties. Config keys are set
     * in config/cache.php and this array will contain only block for the
     * requested cache adapter. Yes, you can also build this manually, but that
     * is not recommended.
     *
     * @param array $config
     *
     * @throws \Koldy\Exception
     */
    public function __construct(array $config)
    {
        // because if cache is not enabled, then lets not do anything else

        if (!isset($config['path']) || $config['path'] === null) {
            $this->path = Application::getStoragePath('cache/');
        } else {
            $this->path = $config['path'];
        }

        if (substr($this->path, -1) != '/') {
            $this->path .= '/';
        }

        parent::__construct($config);
    }

    /**
     * Get path to the cache file by $key
     *
     * @param string $key
     *
     * @return string
     */
    protected function getPath(string $key): string
    {
        return $this->path . $key . '.txt';
    }

    /**
     * Load the data from the file and store it in this request's memory
     *
     * @param string $key
     *
     * @return \stdClass or false if cache doesn't exists
     * @throws CacheException
     */
    protected function load(string $key): \stdClass
    {
        $this->checkKey($key);
        $path = $this->getPath($key);

        if (is_file($path)) {
            $object = new \stdClass;
            $object->path = $path;

            $file = file_get_contents($path);

            $pos = strpos($file, "\n");
            if ($pos === false) {
                // new line not found, means that file might be corrupted
                throw new CacheException("Can not load data for cache key={$key}, file might be corrupted");
            }

            $firstLine = substr($file, 0, $pos);
            $firstLine = explode(';', $firstLine);

            $object->created = strtotime($firstLine[0]);
            $object->seconds = $firstLine[1];
            $object->data = substr($file, strpos($file, "\n") + 1);
            $object->action = null;
            $object->type = $firstLine[2];

            switch ($object->type) {
                case 'array':
                case 'object':
                    $object->data = unserialize($object->data);
                    break;
            }

            $this->data[$key] = $object;
            return $object;
        }

        throw new CacheException("Can not load data for cache key={$key}");
    }

    /**
     * @param string $key
     *
     * @return mixed|null
     */
    public function get(string $key)
    {
        $this->checkKey($key);

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
     * @return mixed[]
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
     * @param string $key
     * @param mixed $value
     * @param int $seconds [optional]
     */
    public function set(string $key, $value, int $seconds = null): void
    {
        $this->checkKey($key);

        if ($seconds === null) {
            $seconds = $this->defaultDuration;
        }

        if (isset($this->data[$key])) {
            $object = $this->data[$key];
        } else {
            $object = new \stdClass;
            $object->path = $this->getPath($key);
        }

        $object->created = time();
        $object->seconds = $seconds;
        $object->data = $value;
        $object->action = 'set';
        $object->type = gettype($value);
        $this->data[$key] = $object;

        $this->initShutdown();
    }

    /**
     * Set multiple values to default cache engine and overwrite if keys already exists
     *
     * @param array $keyValuePairs
     * @param int $seconds
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
        $this->checkKey($key);

        if (!isset($this->data[$key])) {

            try {
                $object = $this->load($key);
            } catch (CacheException $e) {
                return false;
            }
        } else {
            $object = $this->data[$key];
        }

        $ok = $object->created + $object->seconds > time();
        if (!$ok) {
            $this->data[$key]->action = 'delete';
            $this->initShutdown();
        }

        return $ok;
    }

    /**
     * Deletes the item from cache engine
     *
     * @param string $key
     *
     * @throws CacheException
     * @link http://koldy.net/docs/cache#delete
     */
    public function delete(string $key): void
    {
        $this->checkKey($key);

        if (is_file($this->getPath($key))) {
            if (isset($this->data[$key])) {
                $this->data[$key]->action = 'delete';
                $this->initShutdown();
                // cache files will be deleted after request ends
            } else {
                $path = $this->getPath($key);
                if (!@unlink($path)) {
                    throw new CacheException("Unable to delete cache item key={$key} on path={$path}");
                }
            }
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
     * Delete all files under cached folder
     */
    public function deleteAll(): void
    {
        Directory::emptyDirectory($this->path);
    }

    /**
     * @param int $olderThenSeconds
     */
    public function deleteOld(int $olderThenSeconds = null): void
    {
        if ($olderThenSeconds === null) {
            $olderThenSeconds = $this->defaultDuration;
        }

        clearstatcache();

        /**
         * This is probably not good since lifetime is written in file
         * But going into every file and read might be even worse idea
         */
        foreach (Directory::read($this->path) as $fullPath => $fileName) {
            $timeCreated = @filemtime($fullPath);
            if ($timeCreated !== false) {
                // successfully red the file modification time

                if (time() - $olderThenSeconds > $timeCreated) {
                    // it is old enough to be removed

                    if (!@unlink($fullPath)) {
                        Log::warning("Can not delete cached file on path {$fullPath}");
                    }
                }
            }
        }
    }

    /**
     * Initialize the shutdown function when request execution ends
     */
    protected function initShutdown(): void
    {
        if (!$this->shutdown) {
            $this->shutdown = true;
            $self = $this;
            register_shutdown_function(function () use ($self) {
                $self->shutdown();
            });
        }
    }

    /**
     * Execute this method on request's execution end. When you're working with
     * cache, the idea is not to work all the time with the filesystem. All
     * changes (new keys and keys that exists and needs to be deleted) will be
     * applied here, on request shutdown
     */
    public function shutdown(): void
    {
        $checkedFolder = false;

        foreach ($this->data as $key => $object) {
            switch ($object->action) {
                case 'set':
                    switch ($object->type) {
                        default:
                            $data = $object->data;
                            break;

                        case 'array':
                        case 'object':
                            $data = serialize($object->data);
                            break;
                    }

                    if (!$checkedFolder) {
                        $directory = dirname($object->path);

                        if (!is_dir($directory)) {
                            Directory::mkdir($directory, 0755);
                        }

                        $checkedFolder = true;
                    }

                    file_put_contents($object->path, sprintf("%s;%d;%s\n%s", gmdate('r', $object->created), $object->seconds, $object->type, $data));
                    break;

                case 'delete':
                    unlink($object->path);
                    break;
            }
        }
    }

    /**
     * Gets native instance of the adapter on which we're working on. If we're working with Memcached, then you'll
     * get \Memcached class instance. If you're working with files, then you'll get null.
     *
     * @return mixed
     */
    public function getNativeInstance()
    {
        return null;
    }
}
