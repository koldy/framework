<?php declare(strict_types=1);

namespace Koldy\Db\Query;

class Exception extends \Koldy\Db\Exception
{

	/**
	 * @var null|string
	 */
	protected string | null $sql = null;

	/**
	 * @var null|Bindings
	 */
	protected Bindings | null $bindings = null;

	/**
	 * @var null|bool
	 */
	protected bool | null $wasPrepared = null;

	/**
	 * @return null|string
	 */
	public function getSql(): ?string
	{
		return $this->sql;
	}

	/**
	 * @param null|string $sql
	 */
	public function setSql(?string $sql): void
	{
		$this->sql = $sql;
	}

	/**
	 * @return Bindings|null
	 */
	public function getBindings(): ?Bindings
	{
		return $this->bindings;
	}

	/**
	 * @param Bindings|null $bindings
	 */
	public function setBindings(?Bindings $bindings): void
	{
		$this->bindings = $bindings;
	}

	/**
	 * @return bool|null
	 */
	public function getWasPrepared(): ?bool
	{
		return $this->wasPrepared;
	}

	/**
	 * @param bool|null $wasPrepared
	 */
	public function setWasPrepared(?bool $wasPrepared): void
	{
		$this->wasPrepared = $wasPrepared;
	}

}
