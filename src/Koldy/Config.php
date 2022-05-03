<?php declare(strict_types=1);

namespace Koldy;

use Koldy\Config\Exception as ConfigException;

/**
 * Class Config
 *
 * Handles loaded configs from files or created directly
 *
 * @package Koldy
 */
class Config
{

    /**
     * @var array|null
     */
    private array | null $data = null;

    /**
     * @var string|null
     */
    private string | null $path = null;

    /**
     * @var string
     */
    private string $name;

    /**
     * @var int|null
     */
    private int | null $loadedAt = null;

    /**
     * @var bool
     */
    private bool $isPointerConfig;

    /**
     * Config constructor. It is usually used by framework, but you can use it by yourself if you need to
     * handle configs manually.
     *
     * @param string $name
     * @param bool $isPointerConfig - if set to true, then it'll act as database or mail config
     */
    public function __construct(string $name, bool $isPointerConfig = false)
    {
        $this->name = $name;
        $this->isPointerConfig = $isPointerConfig;
    }

    /**
     * Get the config name. Useful if you're dealing with multiple configs by yourself and you want to know which
     * config instance is which.
     *
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Gets full path to config file on file system (if config was loaded from file, null otherwise)
     *
     * @return null|string
     */
    public function getFullPath(): ?string
    {
        return $this->path;
    }

	/**
	 * After config instance is constructed, you should load configuration from file by using this method. Otherwise,
	 * configuration should be set by using set or setData methods.
	 *
	 * @param string $path
	 *
	 * @throws ConfigException
	 */
    public function loadFrom(string $path): void
    {
        $this->path = $path;

        if (is_file($path)) {
            $data = require $path;

            if (!is_array($data)) {
                throw new ConfigException("Config loaded from path={$path} is not an array; please return an array from a loaded PHP file");
            }

			$this->data = $data;
            $this->loadedAt = time();
        } else {
            throw new ConfigException('Unable to load config from path=' . $path);
        }
    }

	/**
	 * Reload configuration from file system if config was loaded from file system
	 * @throws ConfigException
	 */
    public function reload(): void
    {
        if ($this->path !== null) {
            $this->loadFrom($this->path);
        }
    }

    /**
     * Manually sets the configuration data. Be aware that this will override any previously set or loaded config.
     * If configuration was loaded from file, this won't override the file on file system.
     *
     * @param array $data
     */
    final public function setData(array $data): void
    {
        $this->data = $data;
        $this->loadedAt = time();
    }

    /**
     * Returns true if config was set to be "pointer-config"
     *
     * @return bool
     */
    public function isPointerConfig(): bool
    {
        return $this->isPointerConfig;
    }

	/**
	 * Gets the whole configuration array
	 *
	 * @return array
	 * @throws ConfigException
	 */
    public function getData(): array
    {
        if (!is_array($this->data)) {
            throw new ConfigException('Unable to get config data when config wasn\'t loaded for config name=' . $this->name);
        }

        return $this->data;
    }

    /**
     * True if config contains any data
     *
     * @return bool
     */
    public function hasData(): bool
    {
        return $this->data !== null && count($this->data) > 0;
    }

	/**
	 * Returns true if loaded configuration is older then the seconds passed as first argument, `false` otherwise.
	 * This is useful if your CLI script is running for the long time and there's possibility that config
	 * was updated in meantime.
	 *
	 * @param int $numberOfSeconds
	 *
	 * @return bool
	 * @throws ConfigException
	 */
    public function isOlderThan(int $numberOfSeconds): bool
    {
        if ($this->loadedAt == null) {
            throw new ConfigException('Can not know how old is config when config is not set nor loaded yet; Please load config first for config name=' . $this->name);
        }

        return time() - $numberOfSeconds > $this->loadedAt;
    }

	/**
	 * @param int $numberOfSeconds
	 *
	 * @return bool
	 * @throws ConfigException
	 * @deprecated Typo in name, use isOlderThan() instead
	 */
	public function isOlderThen(int $numberOfSeconds): bool
	{
		return $this->isOlderThan($numberOfSeconds);
	}

    /**
     * Sets the value to the key in this configuration instance. If configuration was loaded from filesystem, note
     * that this won't affect file system. It'll just override the config's value in the runtime
     *
     * @param string $key
     * @param mixed $value
     */
    final public function set(string $key, mixed $value): void
    {
        if ($this->data === null) {
            $this->data = [];
        }

        $this->data[$key] = $value;
    }

	/**
	 * Returns true if requested key exists in current configuration, false otherwise.
	 *
	 * @param string $key
	 *
	 * @return bool
	 * @throws ConfigException
	 */
    public function has(string $key): bool
    {
        if (!is_array($this->data)) {
            throw new ConfigException('Unable to get config data when config wasn\'t loaded for config name=' . $this->name);
        }

        return array_key_exists($key, $this->data);
    }

	/**
	 * Gets the value for requested key in the confi
	 *
	 * @param string $key
	 *
	 * @return mixed
	 * @throws ConfigException
	 */
    public function get(string $key): mixed
    {
        if (!is_array($this->data)) {
            throw new ConfigException('Unable to get config data when config wasn\'t loaded for config name=' . $this->name);
        }

        if (!array_key_exists($key, $this->data)) {
            return null;
        }

        if (!$this->isPointerConfig()) {
            return $this->data[$key];
        } else {
            // this is pointer config, let's examine what we have
            $counter = 0;

            do {
                if (!isset($this->data[$key])) {
                    throw new ConfigException("Trying to get non-existing config key={$key} in config name={$this->name()} after {$counter} 'redirects'");
                }

                $config = $this->data[$key];

                if (!is_string($config)) {
                    return $config;
                } else {
                    $key = $config;
                }
            } while ($counter++ < 10);
        }

        // if you get to this line, then something is wrong
        throw new ConfigException("{$this} has too many \"redirects\"");
    }

    /**
     * When config is loaded or set, you can delete the presence of key by providing its name as first argument.
     * This is advanced usage and should be avoided as much as possible. If configuration was loaded from file, this
     * won't alter the file on file system.
     *
     * @param string $key
     */
    public function delete(string $key): void
    {
        if (array_key_exists($key, $this->data)) {
            unset($this->data[$key]);
        }
    }

    /**
     * If targeted key in first level is array, then you can use this to fetch the key from that array
     *
     * @param string $key
     * @param string $subKey
     *
     * @return mixed
     * @throws ConfigException
     * @throws Exception
     */
    public function getArrayItem(string $key, string $subKey): mixed
    {
        $expectedArray = $this->get($key) ?? [];

        if (!is_array($expectedArray)) {
            $type = gettype($expectedArray);
            throw new ConfigException("Trying to fetch array from config={$this->name()} under key={$key}, but got '{$type}' instead");
        }

        return array_key_exists($subKey, $expectedArray) ? $expectedArray[$subKey] : null;
    }

    /**
     * Get the first key in config. Useful for pointer configs.
     *
     * @return string
     * @throws ConfigException
     */
    public function getFirstKey(): string
    {
        $config = $this->data;

        if (count($config) == 0) {
            throw new ConfigException('Unable to get first config key when config is empty');
        }

        $key = array_keys($config)[0];

        if ($this->isPointerConfig()) {
            $counter = 0;
            while (is_string($key) && $counter++ < 50) {
                if (isset($this->data[$key])) {
                    $value = $this->data[$key];
                } else {
                    throw new ConfigException("{$this} found invalid config pointer named={$key}");
                }

                if (is_string($value)) {
                    $key = $value;
                }
            }

            if ($counter == 50) {
                throw new ConfigException('Unable to get first key in config after 50 "redirects"');
            }
        }

        return $key;
    }

    /**
     * Checks the presence of given config keys; if any of required keys is missing, exception will be thrown. If you
     * want to know which keys are missing, then pass false as second argument and you'll get the array of missing keys.
     *
     * @param array $keys
     * @param bool $throwException
     *
     * @return array
     * @throws ConfigException
     */
    public function checkPresence(array $keys, bool $throwException = true): array
    {
        $missingKeys = [];

        foreach ($keys as $key) {
            if (!array_key_exists($key, $this->data)) {
                $missingKeys[] = $key;
            }
        }

        if ($throwException && count($missingKeys) > 0) {
            $missingKeys = implode(', ', $missingKeys);
            throw new ConfigException("Following keys are missing in {$this}: {$missingKeys}");
        }

        return $missingKeys;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return 'Config name=' . $this->name;
    }

}
