<?php declare(strict_types=1);

namespace Koldy\Db\Query;

use BackedEnum;
use Koldy\Db\Expr;

/**
 * Class Bindings is used for easier parameter binding within the SQL statements.
 * It's using PDO's bindValue() method. To keep everything simple, we don't use any
 * bindParam() nor any other reference to value because that might lead to unexpected
 * behaviour which is hard to catch.
 *
 * @package Koldy\Db\Query
 */
class Bindings
{

	/**
	 * Current index for uniqueness on SQL statements
	 *
	 * @var int
	 */
	protected static int $index = 0;

	/**
	 * The actual data for bindings
	 * @var Bind[]
	 */
	protected array $bindings = [];

	public function set(
		string $parameter,
		int|float|bool|string|BackedEnum|null $value,
		int|null $typeConstant = null
	): string {
		$this->bindings[$parameter] = new Bind($parameter, $value, $typeConstant);
		return $parameter;
	}

	/**
	 * @param string|Expr $parameter
	 * @param $value
	 * @param int|null $typeConstant
	 *
	 * @return string
	 */
	public function makeAndSet(string|Expr $parameter, $value, int|null $typeConstant = null): string
	{
		if ($parameter instanceof Expr) {
			// something complex in the field name, so create generic name instead of trying to create human-friendly-readable name
			$bindName = 'expr' . static::getNextIndex();
		} else {
			$bindName = static::make($parameter);
		}

		$this->bindings[$bindName] = new Bind($bindName, $value, $typeConstant);
		return $bindName;
	}

	/**
	 * Get next unique index for binding to SQL statements
	 *
	 * @return int
	 */
	public static function getNextIndex(): int
	{
		if (static::$index === PHP_INT_MAX) {
			/*
			 * If we reach overflow, we'll reset index to zero, because
			 * it's really unlikely that anyone will ever be able to execute
			 * that many binded variables within one SQL statement
			 */
			static::$index = 0;
		}

		return static::$index++;
	}

	/**
	 * Make unique bind name according to given parameter name
	 *
	 * @param string $parameter
	 *
	 * @return string
	 */
	public static function make(string $parameter): string
	{
		$parameter = str_replace('=', '_', $parameter);
		$parameter = str_replace('.', '_', $parameter);
		$parameter = str_replace(',', '_', $parameter);
		$parameter = str_replace(' ', '_', $parameter);
		$parameter = str_replace('-', '_', $parameter);
		$parameter = str_replace('(', '_', $parameter);
		$parameter = str_replace(')', '_', $parameter);
		$parameter = str_replace('*', '', $parameter);
		$parameter = str_replace('>', '_', $parameter);
		$parameter = str_replace('<', '_', $parameter);
		$parameter = str_replace('\'', '', $parameter);
		$parameter = str_replace('/', '', $parameter);
		$parameter = str_replace('"', '', $parameter);
		$parameter = str_replace(':', '_', $parameter);
		$parameter = str_replace('__', '_', $parameter); // first pass
		$parameter = str_replace('__', '_', $parameter); // double
		$parameter = str_replace('__', '_', $parameter); // triple

		if (strlen($parameter) > 25) {
			$parameter = substr($parameter, 0, 25);
		}

		return strtolower($parameter) . static::getNextIndex();
	}

	/**
	 * Get already binded parameter
	 *
	 * @param string $parameter
	 *
	 * @return Bind
	 * @throws Exception
	 */
	public function get(string $parameter): Bind
	{
		if (!$this->has($parameter)) {
			throw new Exception("Bind name \"{$parameter}\" does not exists");
		}

		return $this->bindings[$parameter];
	}

	/**
	 * @param string $parameter
	 *
	 * @return bool
	 */
	public function has(string $parameter): bool
	{
		return isset($this->bindings[$parameter]);
	}

	/**
	 * @param array $bindings
	 */
	public function addBindings(array $bindings): void
	{
		$this->bindings = array_merge($this->bindings, $bindings);
	}

	/**
	 * @param Bind $bind
	 */
	public function addBind(Bind $bind): void
	{
		$this->bindings[$bind->getParameter()] = clone $bind;
	}

	/**
	 * @param Bindings $bindings
	 */
	public function addBindingsFromInstance(self $bindings): void
	{
		$this->bindings = array_merge($this->bindings, $bindings->getBindings());
	}

	/**
	 * Get the array of all bindings where key is the parameter name and value is instance of Bind
	 *
	 * @return Bind[]
	 */
	public function getBindings(): array
	{
		return $this->bindings;
	}

	/**
	 * Gets all bindings as key value assoc array where key is parameter and value is its value.
	 * @return array
	 */
	public function getAsArray(): array
	{
		$data = [];

		foreach ($this->getBindings() as $bind) {
			$data[$bind->getParameter()] = $bind->getValue();
		}

		return $data;
	}

	/**
	 * Set the bindings by providing assoc array of parameter => value
	 *
	 * @param array $data
	 */
	public function setFromArray(array $data): void
	{
		foreach ($data as $parameter => $value) {
			$this->bindings[$parameter] = new Bind($parameter, $value);
		}
	}

	/**
	 * Returns true if nothing was binded
	 *
	 * @return bool
	 */
	public function isEmpty(): bool
	{
		return count($this->bindings) === 0;
	}
}
