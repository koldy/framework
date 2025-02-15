<?php declare(strict_types=1);

namespace Koldy\Db\ModelTraits;

/**
 * Trait for models that has the "id" column which is integer. Suitable for primary keys in DB tables.
 * @package Koldy\Db\ModelTraits
 *
 * @property int|null $id
 *
 * @deprecated This trait is deprecated and will be removed in next major version.
 */
// @phpstan-ignore-next-line
trait Id
{

	/**
	 * Get the ID or null.
	 *
	 * @return int|null
	 */
	public function getId(): ?int
	{
		if ($this->id === null) {
			return null;
		}

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
	 * @param int|null $id
	 */
	public function setId(?int $id): void
	{
		$this->id = $id;
	}

}
