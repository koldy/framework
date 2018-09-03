<?php declare(strict_types=1);

namespace Koldy\Db\Query;

use PDO;

/**
 * Class Bind holds the information about binded value within Bindings class.
 *
 * @package Koldy\Db\Query
 * @see Bindings
 */
class Bind
{

	/**
	 * @var string
	 */
	private $parameter;

	/**
	 * @var mixed
	 */
	private $value;

	/**
	 * @var int
	 */
	private $type;

	/**
	 * Bind constructor.
	 *
	 * @param string $parameter
	 * @param mixed $value
	 * @param int|null $typeConstant
	 */
	public function __construct(string $parameter, $value, int $typeConstant = null)
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

	/**
	 * @return string
	 */
	public function getParameter(): string
	{
		return $this->parameter;
	}

	/**
	 * @return mixed
	 */
	public function getValue()
	{
		return $this->value;
	}

	/**
	 * @return int
	 */
	public function getType(): int
	{
		return $this->type;
	}

}
