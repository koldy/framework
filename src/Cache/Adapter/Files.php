<?php declare(strict_types=1);

namespace Koldy\Cache\Adapter;

use Closure;
use Koldy\Application;
use Koldy\Cache\ConnectionException as CacheConnectionException;
use Koldy\Cache\Exception as CacheException;
use Koldy\Exception;
use Koldy\Filesystem\Directory;
use Koldy\Filesystem\Exception as FilesystemException;
use Koldy\Log;
use stdClass;

/**
 * This cache adapter will store all of your data into files somewhere on the server's filesystem. Every stored key
 * represents one file on filesystem.
 *
 * @link https://koldy.net/framework/docs/2.0/cache/files.md
 */
class Files extends AbstractCacheAdapter
{

	/**
	 * The path to the folder where cache files will be stored
	 *
	 * @var string
	 */
	protected string $path;

	/**
	 * The array of loaded and/or data that will be stored
	 *
	 * @var array
	 */
	protected array $data = [];

	/**
	 * Flag if folder was already checked if exists
	 *
	 * @var bool
	 */
	protected bool $checkedFolder = false;

	/**
	 * Construct the object by array of config properties. Config keys are set
	 * in config/cache.php and this array will contain only block for the
	 * requested cache adapter. Yes, you can also build this manually, but that
	 * is not recommended.
	 *
	 * @param array $config
	 *
	 */
	public function __construct(array $config)
	{
		// because if cache is not enabled, then lets not do anything else

		if (!isset($config['path'])) {
			$this->path = Application::getStoragePath('cache/');
		} else {
			$this->path = $config['path'];
		}

		if (!str_ends_with($this->path, '/')) {
			$this->path .= '/';
		}

		parent::__construct($config);
	}

	/**
	 * Read PHP's last error message (set by failing filesystem functions like
	 * file_put_contents, mkdir, unlink) and return it as a human-readable
	 * reason. Falls back to a generic message if no error has been captured —
	 * which can happen if a previous error_clear_last() ran or the underlying
	 * function did not emit a warning. Callers should call error_clear_last()
	 * immediately before the operation they want to inspect, to guarantee
	 * that any reported reason corresponds to the operation that just failed.
	 *
	 * @param string $fallback returned when error_get_last() has nothing useful
	 *
	 * @return string
	 */
	private function lastErrorReason(string $fallback = 'unknown reason'): string
	{
		$err = error_get_last();
		return ($err !== null && $err['message'] !== '') ? $err['message'] : $fallback;
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
	 * @param string $key
	 *
	 * @return mixed|null
	 */
	public function get(string $key): mixed
	{
		$this->checkKey($key);

		if ($this->has($key)) {
			return $this->data[$key]->data;
		}

		return null;
	}

	/**
	 * @param string $key
	 *
	 * @return bool
	 * @throws CacheConnectionException when the underlying file exists but cannot
	 *                                  be read; missing or corrupt files yield
	 *                                  false (cache miss) without throwing
	 */
	public function has(string $key): bool
	{
		$this->checkKey($key);

		if (!isset($this->data[$key])) {

			try {
				$object = $this->load($key);
			} catch (CacheConnectionException $e) {
				// infra failure — let it propagate so failover can take over
				throw $e;
			} catch (CacheException $ignored) {
				// file missing or corrupt — treat as cache miss
				return false;
			}
		} else {
			$object = $this->data[$key];
		}

		/** @var int $created */
		$created = $object->created;

		/** @var int $seconds */
		$seconds = $object->seconds;

		$ok = $created + $seconds > time();
		if (!$ok) {
			unlink($object->path);
		}

		return $ok;
	}

	/**
	 * Load the data from the file and store it in this request's memory
	 *
	 * @param string $key
	 *
	 * @return stdClass
	 * @throws CacheConnectionException when the file exists but cannot be read
	 *                                  (I/O error, permission denied, …) — these
	 *                                  bubble up so the failover proxy can catch
	 *                                  them and switch to the next adapter
	 * @throws CacheException when the file does not exist (cache miss) or its
	 *                        contents are corrupted
	 */
	protected function load(string $key): stdClass
	{
		$this->checkKey($key);
		$path = $this->getPath($key);

		if (!is_file($path)) {
			// missing file is just a cache miss — has() catches this and returns false
			throw new CacheException("Can not load data for cache key={$key}");
		}

		error_clear_last();
		// '@' suppresses the PHP warning that file_get_contents emits on
		// failure; we explicitly read the reason via error_get_last() below
		// and surface it in the exception message, so the warning would only
		// add noise (and fails tests that run with failOnWarning=true).
		$file = @file_get_contents($path);

		if ($file === false) {
			// file exists but read failed — likely permission denied, I/O error,
			// or filesystem unmounted; let failover handle it
			$reason = $this->lastErrorReason();
			throw new CacheConnectionException("Cache file read failed for key '{$key}' at path '{$path}': {$reason}");
		}

		$pos = strpos($file, "\n");
		if ($pos === false) {
			// new line not found, means that file might be corrupted
			throw new CacheException("Can not load data for cache key={$key}, file might be corrupted");
		}

		$object = new stdClass;
		$object->path = $path;

		$firstLine = substr($file, 0, $pos);
		$firstLine = explode(';', $firstLine);

		$object->created = strtotime($firstLine[0]);
		$object->seconds = $firstLine[1];
		$object->data = substr($file, $pos + 1);
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
	 * Set multiple values to default cache engine and overwrite if keys already exists
	 *
	 * @param array $keyValuePairs
	 * @param int|null $seconds
	 *
	 * @throws \Koldy\Config\Exception
	 * @throws Exception
	 * @throws FilesystemException
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
	 * @param mixed $value
	 * @param int|null $seconds [optional]
	 *
	 * @throws CacheConnectionException when writing to disk fails (permission
	 *                                  denied, no space left on device, read-only
	 *                                  filesystem, …) or when the cache directory
	 *                                  cannot be auto-created
	 * @throws \Koldy\Config\Exception
	 * @throws Exception
	 */
	public function set(string $key, mixed $value, int|null $seconds = null): void
	{
		$this->checkKey($key);

		if ($seconds === null) {
			$seconds = $this->defaultDuration;
		}

		if (isset($this->data[$key])) {
			$object = $this->data[$key];
		} else {
			$object = new stdClass;
			$object->path = $this->getPath($key);
		}

		$object->created = time();
		$object->seconds = $seconds;
		$object->data = $value;
		$object->type = gettype($value);
		$this->data[$key] = $object;

		switch ($object->type) {
			default:
				$data = $object->data;
				break;

			case 'array':
			case 'object':
				$data = serialize($object->data);
				break;
		}

		if (!$this->checkedFolder) {
			$directory = dirname($object->path);

			if (!is_dir($directory)) {
				// Directory::mkdir uses raw mkdir() which emits a PHP warning on
				// failure (e.g. "Permission denied", "No space left on device").
				// error_get_last() captures that warning so we can include the
				// real underlying reason in the exception message rather than a
				// generic "filesystem level" line. The '@' suppresses the
				// warning's display since we surface it via the exception.
				error_clear_last();

				try {
					@Directory::mkdir($directory, 0755);
				} catch (FilesystemException $e) {
					$reason = $this->lastErrorReason($e->getMessage());
					throw new CacheConnectionException("Couldn't store cache key \"{$key}\" because target directory \"{$directory}\" doesn't exist and could not be created: {$reason}",
						$e->getCode(), $e);
				}
			}

			$this->checkedFolder = true;
		}

		error_clear_last();
		$payload = sprintf("%s;%d;%s\n%s", gmdate('r', $object->created), $object->seconds, $object->type, $data);

		// '@' suppresses the PHP warning on write failure; we read the underlying
		// reason via error_get_last() and surface it in the exception below.
		if (@file_put_contents($object->path, $payload) === false) {
			$reason = $this->lastErrorReason();
			throw new CacheConnectionException("Couldn't store cache key \"{$key}\" at path \"{$object->path}\": {$reason}");
		}
	}

	/**
	 * Delete multiple items from cache engine
	 *
	 * @param array $keys
	 *
	 * @throws CacheException
	 * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
	 */
	public function deleteMulti(array $keys): void
	{
		foreach ($keys as $key) {
			$this->delete($key);
		}
	}

	/**
	 * Deletes the item from cache engine
	 *
	 * @param string $key
	 *
	 * @throws CacheConnectionException when the file exists but cannot be unlinked
	 *                                  (permission denied, read-only filesystem, …)
	 * @link https://koldy.net/framework/docs/2.0/cache.md#working-with-cache
	 */
	public function delete(string $key): void
	{
		$this->checkKey($key);
		$path = $this->getPath($key);

		if (!is_file($path)) {
			// nothing to delete; deleting a non-existent key is not an error
			unset($this->data[$key]);
			return;
		}

		error_clear_last();

		if (!@unlink($path)) {
			$reason = $this->lastErrorReason();
			throw new CacheConnectionException("Unable to delete cache key \"{$key}\" at path \"{$path}\": {$reason}");
		}

		unset($this->data[$key]);
	}

	/**
	 * Delete all files under cached folder
	 *
	 * @throws CacheConnectionException when the directory cannot be emptied
	 *                                  (permission denied, file in use, …)
	 */
	public function deleteAll(): void
	{
		// Directory::emptyDirectory uses raw unlink()/rmdir() which emit PHP
		// warnings on failure; error_get_last() captures the underlying reason.
		// '@' suppresses the warning display since we surface it via the
		// thrown exception.
		error_clear_last();

		try {
			@Directory::emptyDirectory($this->path);
		} catch (FilesystemException $e) {
			$reason = $this->lastErrorReason($e->getMessage());
			throw new CacheConnectionException("Couldn't empty cache directory \"{$this->path}\": {$reason}", $e->getCode(), $e);
		}

		// Drop any in-memory entries so they don't shadow the now-empty disk state
		$this->data = [];
	}

	/**
	 * @param int|null $olderThanSeconds
	 *
	 * @throws FilesystemException
	 */
	public function deleteOld(int|null $olderThanSeconds = null): void
	{
		if ($olderThanSeconds === null) {
			$olderThanSeconds = $this->defaultDuration;
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

				if (time() - $olderThanSeconds > $timeCreated) {
					// it is old enough to be removed

					if (!@unlink($fullPath)) {
						Log::warning("Can not delete cached file on path {$fullPath}");
					}
				}
			}
		}
	}

}
