<?php declare(strict_types=1);

namespace Koldy\Db\ModelTraits;

use DateTime;
use DateTimeZone;
use Koldy\Exception;

/**
 * Trait UpdatedAt
 * @package Koldy\Db\ModelTraits
 *
 * @property string|null $updated_at
 *
 * @deprecated This trait is deprecated and will be removed in next major version.
 */
// @phpstan-ignore-next-line
trait UpdatedAt
{

    /**
     * @return bool
     */
    public function hasUpdatedAt(): bool
    {
	    return is_string($this->updated_at) && strlen($this->updated_at) > 0;
    }

    /**
     * @return string|null
     */
    public function getUpdatedAt(): ?string
    {
        return $this->updated_at;
    }

	/**
	 * @param string|null $timezone
	 *
	 * @return DateTime|null
	 * @throws \Exception
	 */
    public function getUpdatedAtDatetime(string $timezone = null): ?DateTime
    {
        if (!$this->hasUpdatedAt()) {
            return null;
        }

        if ($timezone === null) {
            $timezone = 'UTC';
        }

        return new DateTime($this->getUpdatedAt(), new DateTimeZone($timezone ?? 'UTC'));
    }

	/**
	 * Get the timestamp of the created_at value
	 *
	 * @return int|null
	 * @throws Exception
	 */
    public function getUpdatedAtTimestamp(): ?int
    {
        if (!$this->hasUpdatedAt()) {
            return null;
        }

        $timestamp = strtotime($this->getUpdatedAt() . 'UTC');

        if ($timestamp === false) {
        	throw new Exception("Unable to get timestamp from \"{$this->getUpdatedAt()}\"");
        }

        return $timestamp;
    }

	/**
	 * Sets the updated at value
	 *
	 * @param DateTime|string|null $updatedAt - Pass the SQL's Y-m-d H:i:s or Y-m-d value, or leave undefined
	 */
    public function setUpdatedAt(DateTime | string | null $updatedAt): void
    {
		if ($updatedAt instanceof DateTime) {
			$this->updated_at = $updatedAt->format('Y-m-d H:i:s');
		} else {
			$this->updated_at = $updatedAt;
		}
    }
}
