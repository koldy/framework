<?php declare(strict_types=1);

namespace Koldy\Db\ModelTraits;

/**
 * Trait Label - very common in database tables, usually used for naming records.
 * @package Koldy\Db\ModelTraits
 *
 * @property string|null label
 */
trait Label
{

	/**
	 * @return bool
	 */
	public function hasLabel(): bool
	{
		return is_string($this->label) && strlen($this->label) > 0;
	}

	/**
	 * @return null|string
	 */
	public function getLabel(): ?string
	{
		return $this->label ?? null;
	}

	/**
	 * @param null|string $label
	 */
	public function setLabel(?string $label): void
	{
		$this->label = $label;
	}

}
