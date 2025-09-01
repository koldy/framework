<?php declare(strict_types=1);

namespace Koldy\Db\ModelTraits;

use DateTime;
use DateTimeZone;
use Koldy\Exception;

/**
 * Trait CreatedAt
 * @package Koldy\Db\ModelTraits
 *
 * @property string|null $created_at
 *
 * @deprecated This trait is deprecated and will be removed in next major version.
 */
// @phpstan-ignore-next-line
trait CreatedAt
{

	/**
	 * @param int $seconds
	 * @param string|null $timezone
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function isCreatedInLast(int $seconds = 86400, string $timezone = null): bool
	{
		if (!$this->hasCreatedAt()) {
			return false;
		}

		if ($timezone === null) {
			$timezone = 'UTC';
		}

		$date = $this->getCreatedAtDatetime($timezone);
		$now = new DateTime('now', new DateTimeZone($timezone));
		$now->modify("-{$seconds} seconds");

		return $date->getTimestamp() >= $now->getTimestamp();
	}

	/**
	 * @return bool
	 */
	public function hasCreatedAt(): bool
	{
		return is_string($this->created_at) && strlen($this->created_at) > 0;
	}

	/**
	 * @param string|null $timezone
	 *
	 * @return DateTime|null
	 * @throws \Exception
	 */
	public function getCreatedAtDatetime(string $timezone = null): ?DateTime
	{
		if ($this->created_at === null) {
			return null;
		}

		return new DateTime($this->created_at, new DateTimeZone($timezone ?? 'UTC'));
	}

	/**
	 * Get the timestamp of the created_at value
	 *
	 * @return int|null
	 * @throws Exception
	 */
	public function getCreatedAtTimestamp(): ?int
	{
		if (!$this->hasCreatedAt()) {
			return null;
		}

		$timestamp = strtotime($this->getCreatedAt() . 'UTC');

		if ($timestamp === false) {
			throw new Exception("Unable to get timestamp from \"{$this->getCreatedAt()}\"");
		}

		return $timestamp;
	}

	/**
	 * @return string|null
	 */
	public function getCreatedAt(): ?string
	{
		return $this->created_at;
	}

	/**
	 * Sets the created at value
	 *
	 * @param DateTime|string|null $createdAt - if string, then pass the SQL's Y-m-d H:i:s or Y-m-d value
	 */
	public function setCreatedAt(string|DateTime|null $createdAt): void
	{
		if ($createdAt instanceof DateTime) {
			$this->created_at = $createdAt->format('Y-m-d H:i:s');
		} else {
			$this->created_at = $createdAt;
		}
	}
}
