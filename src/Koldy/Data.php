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
    private $data = [];

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
     *
     * @return $this
     */
    final public function setData(array $data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Add/append data to already existing data by using array_merge().
     *
     * @param array $data
     *
     * @return $this
     */
    final public function addData(array $data)
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    /**
     * Set the key into JSON response
     *
     * @param string $key
     * @param mixed $value
     *
     * @return $this
     * @link http://koldy.net/docs/json#usage
     */
    final public function set(string $key, $value)
    {
        $this->data[$key] = $value;
        return $this;
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
     * @return $this
     * @link http://koldy.net/docs/json#usage
     */
    final public function delete(string $key)
    {
        unset($this->data[$key]);
        return $this;
    }

    /**
     * Remove all data that was stored in this instance
     * @return $this
     */
    final public function deleteAll()
    {
        $this->data = [];
        return $this;
    }

    /**
     * Get the key from JSON data
     *
     * @param string $key
     *
     * @return mixed or null if key doesn't exist
     */
    final public function get(string $key)
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Just setter ...
     *
     * @param string $key
     * @param mixed $value
     */
    final public function __set($key, $value)
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
    final public function __get($key)
    {
        return $this->get($key);
    }

}
