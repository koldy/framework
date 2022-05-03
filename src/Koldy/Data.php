<?php declare(strict_types=1);

namespace Koldy;

/**
 * Class Data - Nice shorthand to use "data" methods in other classes
 * @package Koldy
 */
trait Data
{

    /**
     * @var array
     */
    private array $data = [];

    /**
     * Get all data
     *
     * @return array
     */
    final public function getData(): array
    {
        return $this->data;
    }

    /**
     * Set data - it'll override all existing data. If you want to "append" data, use addData() method.
     *
     * @param array $data
     */
    final public function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * Add/append data to already existing data by using array_merge().
     *
     * @param array $data
     */
    final public function addData(array $data): void
    {
        $this->data = array_merge($this->data, $data);
    }

    /**
     * Set the key into JSON response
     *
     * @param string $key
     * @param mixed $value
     *
     * @link http://koldy.net/docs/json#usage
     */
    final public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Is there key set in JSON data
     *
     * @param string $key
     *
     * @return boolean
     * @link http://koldy.net/docs/json#usage
     */
    final public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Remove the JSON key from data
     *
     * @param string $key
     *
     * @link http://koldy.net/docs/json#usage
     */
    final public function delete(string $key): void
    {
        unset($this->data[$key]);
    }

    /**
     * Remove all data that was stored in this instance
     */
    final public function deleteAll(): void
    {
        $this->data = [];
    }

    /**
     * Get the key from JSON data
     *
     * @param string $key
     *
     * @return mixed or null if key doesn't exist
     */
    final public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Just setter ...
     *
     * @param string $key
     * @param mixed $value
     */
    final public function __set(string $key, mixed $value)
    {
        $this->set($key, $value);
    }

    /**
     * And just getter ...
     *
     * @param string $key
     *
     * @return mixed
     */
    final public function __get(string $key): mixed
    {
        return $this->get($key);
    }

}
