<?php declare(strict_types = 1);

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
     * @var array
     */
    private $data = null;

    /**
     * @var string|null
     */
    private $path = null;

    /**
     * @var string
     */
    private $name;

    /**
     * @var int
     */
    private $loadedAt = null;

    /**
     * @var bool
     */
    private $isPointerConfig;

    /**
     * Config constructor.
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
     * Get the config name
     *
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Get full path to config file on file system (if config was loaded from file, null otherwise)
     *
     * @return null|string
     */
    public function getFullPath(): ?string
    {
        return $this->path;
    }

    /**
     * @param string $path
     *
     * @throws Exception
     */
    public function loadFrom(string $path): void
    {
        $this->path = $path;

        if (is_file($path)) {
            $this->data = require $path;

            if (!is_array($this->data)) {
                throw new ConfigException("Config loaded from path={$path} is not an array");
            }

            $this->loadedAt = time();
        } else {
            throw new ConfigException('Unable to load config from path=' . $path);
        }
    }

    /**
     * Reload configuration from file system, if it was loaded from file system
     */
    public function reload(): void
    {
        if ($this->path !== null) {
            $this->loadFrom($this->path);
        }
    }

    /**
     * @param array $data
     */
    final public function setData(array $data): void
    {
        $this->data = $data;
        $this->loadedAt = time();
    }

    /**
     * @return bool
     */
    public function isPointerConfig(): bool
    {
        return $this->isPointerConfig;
    }

    /**
     * @return array
     * @throws Exception
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
     * @param int $numberOfSeconds
     *
     * @return bool
     * @throws Exception
     */
    public function isOlderThen(int $numberOfSeconds): bool
    {
        if ($this->loadedAt == null) {
            throw new ConfigException('Can not know how old is config when config is not set nor loaded yet; Please load config first for config name=' . $this->name);
        }

        return time() - $numberOfSeconds > $this->loadedAt;
    }

    /**
     * @param string $key
     * @param $value
     *
     * @throws Exception
     */
    final public function set(string $key, $value): void
    {
        if (!is_array($this->data)) {
            throw new ConfigException('Unable to get config data when config wasn\'t loaded for config name=' . $this->name);
        }

        $this->data[$key] = $value;
    }

    /**
     * @param string $key
     *
     * @return bool
     * @throws Exception
     */
    public function has(string $key): bool
    {
        if (!is_array($this->data)) {
            throw new ConfigException('Unable to get config data when config wasn\'t loaded for config name=' . $this->name);
        }

        return array_key_exists($key, $this->data);
    }

    /**
     * @param string $key
     * @param mixed $defaultValue
     *
     * @return mixed
     * @throws Exception
     */
    public function get(string $key, $defaultValue = null)
    {
        if (!is_array($this->data)) {
            throw new ConfigException('Unable to get config data when config wasn\'t loaded for config name=' . $this->name);
        }

        if (!array_key_exists($key, $this->data)) {
            return $defaultValue;
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
     * @param string $key
     */
    public function delete(string $key): void
    {
        if (array_key_exists($key, $this->data)) {
            unset($this->data[$key]);
        }
    }

    /**
     * @param string $key
     * @param string $subKey
     * @param mixed|null $defaultValue
     *
     * @return mixed
     * @throws ConfigException
     */
    public function getArrayItem(string $key, string $subKey, $defaultValue = null)
    {
        $expectedArray = $this->get($key, []);

        if (!is_array($expectedArray)) {
            $type = gettype($expectedArray);
            throw new ConfigException("Trying to fetch array from config={$this->name()} under key={$key}, but got '{$type}' instead");
        }

        return array_key_exists($subKey, $expectedArray) ? $expectedArray[$subKey] : $defaultValue;
    }

    /**
     * Get the first key in config
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
                throw new ConfigException('Unable to get first key in config after 50 \'redirects\'');
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
