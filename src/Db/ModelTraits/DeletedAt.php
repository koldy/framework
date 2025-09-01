<?php declare(strict_types=1);

namespace Koldy\Db\ModelTraits;

use DateTime;
use DateTimeZone;
use Koldy\Exception;

/**
 * Trait DeletedAt
 * @package Koldy\Db\ModelTraits
 *
 * @property string|null $deleted_at
 *
 * @deprecated This trait is deprecated and will be removed in next major version.
 */
// @phpstan-ignore-next-line
trait DeletedAt
{

	/**
	 * @param string|null $timezone
	 *
	 * @return DateTime|null
	 * @throws \Exception
	 */
	public function getDeletedAtDatetime(string $timezone = null): ?DateTime
	{
		if (!$this->isDeleted()) {
			return null;
		}

		return new DateTime($this->getDeletedAt(), new DateTimeZone($timezone ?? 'UTC'));
	}

	/**
	 * @return bool
	 */
	public function isDeleted(): bool
	{
		return is_string($this->deleted_at) && strlen($this->deleted_at) > 0;
	}

	/**
	 * @return string|null
	 */
	public function getDeletedAt(): ?string
	{
		return $this->deleted_at;
	}

	/**
	 * Get the timestamp of the created_at value
	 *
	 * @return int|null
	 * @throws Exception
	 */
	public function getDeletedAtTimestamp(): ?int
	{
		if (!$this->isDeleted()) {
			return null;
		}

		$timestamp = strtotime($this->getDeletedAt() . 'UTC');

		if ($timestamp === false) {
			throw new Exception("Unable to get timestamp from \"{$this->getDeletedAt()}\"");
		}

		return $timestamp;
	}

	/**
	 * Sets the deleted at value
	 *
	 * @param DateTime|string|null $deletedAt - Pass the SQL's Y-m-d H:i:s or Y-m-d value, or leave undefined
	 */
	public function setDeletedAt(DateTime|string|null $deletedAt): void
	{
		if ($deletedAt instanceof DateTime) {
			$this->deleted_at = $deletedAt->format('Y-m-d H:i:s');
		} else {
			$this->deleted_at = $deletedAt;
		}
	}
}
