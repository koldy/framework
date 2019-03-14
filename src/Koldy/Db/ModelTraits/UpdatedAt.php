<?php declare(strict_types=1);

namespace Koldy\Db\ModelTraits;

use DateTime;
use DateTimeZone;
use Koldy\Exception;

/**
 * Trait UpdatedAt
 * @package Koldy\Db\ModelTraits
 *
 * @property string updated_at
 */
trait UpdatedAt
{

    /**
     * @return bool
     */
    public function hasUpdatedAt(): bool
    {
	    return $this->updated_at !== null && is_string($this->updated_at) && strlen($this->updated_at) > 0;
    }

    /**
     * @return string
     */
    public function getUpdatedAt(): ?string
    {
        if (!$this->hasUpdatedAt()) {
            return null;
        }

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

        return new DateTime($this->getUpdatedAt(), new DateTimeZone($timezone));
    }

	/**
	 * Get the timestamp of the created_at value
	 *
	 * @return int
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
     * @param string $updatedAt - Pass the SQL's Y-m-d H:i:s or Y-m-d value, or leave undefined
     */
    public function setUpdatedAt(?string $updatedAt): void
    {
        $this->updated_at = $updatedAt;
    }

    /**
     * Set the updated at date time by passing instance of updatedAt
     *
     * @param DateTime $updatedAt
     */
    public function setUpdatedAtDateTime(?DateTime $updatedAt): void
    {
        $this->updated_at = $updatedAt === null ? null : $updatedAt->format('Y-m-d H:i:s');
    }

}
