<?php declare(strict_types=1);

namespace Koldy\Db\ModelTraits;

use DateTime;
use DateTimeZone;

/**
 * Trait CreatedAt
 * @package Koldy\Db\ModelTraits
 *
 * @property string created_at
 */
trait CreatedAt
{

    /**
     * @return bool
     */
    public function hasCreatedAt(): bool
    {
        return $this->created_at !== null && is_string($this->created_at) && strlen($this->created_at) > 0;
    }

    /**
     * @return string
     */
    public function getCreatedAt(): string
    {
        return $this->created_at;
    }

	/**
	 * @param string|null $timezone
	 *
	 * @return DateTime
	 * @throws \Exception
	 */
    public function getCreatedAtDatetime(string $timezone = null): DateTime
    {
        if ($timezone === null) {
            $timezone = 'UTC';
        }
        return new DateTime($this->getCreatedAt(), new DateTimeZone($timezone));
    }

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
     * Get the timestamp of the created_at value
     *
     * @return int
     */
    public function getCreatedAtTimestamp(): ?int
    {
        if (!$this->hasCreatedAt()) {
            return null;
        }

        return strtotime($this->getCreatedAt() . 'UTC');
    }

    /**
     * Sets the created at value
     *
     * @param string $createdAt - Pass the SQL's Y-m-d H:i:s or Y-m-d value
     * @return $this
     */
    public function setCreatedAt(string $createdAt)
    {
        $this->created_at = $createdAt;
        return $this;
    }

    /**
     * Set the created at date time by passing instance of createdAt
     *
     * @param DateTime $createdAt
     * @return $this
     */
    public function setCreatedAtDateTime(DateTime $createdAt)
    {
        $this->created_at = $createdAt->format('Y-m-d H:i:s');
        return $this;
    }

}
