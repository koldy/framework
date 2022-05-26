<?php declare(strict_types=1);

namespace Koldy\Db;

class Exception extends \Koldy\Exception
{

	/**
	 * @var null|string
	 */
	protected string | null $adapter = null;

	/**
	 * Get the name of DB adapter on which query was performed
	 *
	 * @return null|string
	 */
	public function getAdapter(): ?string
	{
		return $this->adapter;
	}

	/**
	 * @param null|string $adapter
	 */
	public function setAdapter(?string $adapter): void
	{
		$this->adapter = $adapter;
	}

}
