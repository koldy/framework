<?php declare(strict_types=1);

namespace Koldy\Db\Query;

use BackedEnum;
use PDO;

/**
 * Class Bind holds the information about binded value within Bindings class.
 *
 * @package Koldy\Db\Query
 * @see Bindings
 */
class Bind
{

	private string $parameter;

	private int | float | bool | string | BackedEnum | null $value;

	private int $type;

	/**
	 * Bind constructor.
	 *
	 * @param string $parameter
	 * @param int|float|bool|string|BackedEnum|null $value
	 * @param int|null $typeConstant
	 */
	public function __construct(string $parameter, int | float | bool | string | BackedEnum | null $value, int|null $typeConstant = null)
	{
		$this->parameter = $parameter;
		$this->value = $value;

		if ($typeConstant !== null) {
			if (!in_array($typeConstant, [PDO::PARAM_NULL, PDO::PARAM_BOOL, PDO::PARAM_INT, PDO::PARAM_STR])) {
				throw new \InvalidArgumentException('Invalid $typeConstant provided: ' . $typeConstant);
			}

			$this->type = $typeConstant;
		} else {
			if ($value === null) {
				$this->type = PDO::PARAM_NULL;
			} else if (is_bool($value)) {
				$this->type = PDO::PARAM_BOOL;
			} else if (is_int($value)) {
				$this->type = PDO::PARAM_INT;
			} else {
				$this->type = PDO::PARAM_STR;
			}
		}
	}

	public function getParameter(): string
	{
		return $this->parameter;
	}

	public function getValue(): int | float | bool | string | null
	{
		if ($this->value instanceof BackedEnum) {
			return $this->value->value;
		}

		return $this->value;
	}

	public function getType(): int
	{
		return $this->type;
	}

}
