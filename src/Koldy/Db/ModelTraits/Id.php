<?php declare(strict_types=1);

namespace Koldy\Db\ModelTraits;

/**
 * Trait for models that has the "id" column which is integer. Suitable for primary keys in DB tables.
 * @package Koldy\Db\ModelTraits
 *
 * @property int id
 */
trait Id
{

	/**
	 * Get the ID
	 *
	 * @return int
	 */
	public function getId(): int
	{
		return (int)$this->id;
	}

	/**
	 * Is ID set or not
	 *
	 * @return bool
	 */
	public function hasId(): bool
	{
		return $this->id !== null;
	}

	/**
	 * Set the ID
	 *
	 * @param int $id
	 */
	public function setId(int $id): void
	{
		$this->id = $id;
	}

}
