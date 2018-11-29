<?php declare(strict_types=1);

namespace Koldy\Db\ModelTraits;

use DateTime;
use DateTimeZone;

/**
 * Trait DeletedAt
 * @package Koldy\Db\ModelTraits
 *
 * @property string deleted_at
 */
trait DeletedAt
{

    /**
     * @return bool
     */
    public function isDeleted(): bool
    {
	    return $this->deleted_at !== null && is_string($this->deleted_at) && strlen($this->deleted_at) > 0;
    }

    /**
     * @return string
     */
    public function getDeletedAt(): ?string
    {
        if (!$this->isDeleted()) {
            return null;
        }

        return $this->deleted_at;
    }

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

        if ($timezone === null) {
            $timezone = 'UTC';
        }
        return new DateTime($this->getDeletedAt(), new DateTimeZone($timezone));
    }

    /**
     * Get the timestamp of the created_at value
     *
     * @return int
     */
    public function getDeletedAtTimestamp(): ?int
    {
        if (!$this->isDeleted()) {
            return null;
        }

        return strtotime($this->getDeletedAt() . 'UTC');
    }

    /**
     * Sets the deleted at value
     *
     * @param string $deletedAt - Pass the SQL's Y-m-d H:i:s or Y-m-d value, or leave undefined
     */
    public function setDeletedAt(?string $deletedAt): void
    {
        $this->deleted_at = $deletedAt;
    }

    /**
     * Set the deleted at date time by passing instance of deletedAt
     *
     * @param DateTime $deletedAt
     */
    public function setDeletedAtDateTime(DateTime $deletedAt): void
    {
        $this->deleted_at = $deletedAt->format('Y-m-d H:i:s');
    }

}
