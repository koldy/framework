<?php declare(strict_types=1);

namespace Db\ModelTraits;

use DateTime;
use DateTimeZone;
use Koldy\Application;

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
    public function isUpdated(): bool
    {
        return $this->updated_at !== null && is_string($this->updated_at) && strlen($this->updated_at) > 0;
    }

    /**
     * @return bool
     * @alias \Koldy\Db\ModelTraits\UpdatedAt::isUpdated()
     */
    public function hasUpdatedAt(): bool
    {
        return $this->isUpdated();
    }

    /**
     * @return string
     */
    public function getUpdatedAt(): ?string
    {
        if (!$this->isUpdated()) {
            return null;
        }

        return $this->updated_at;
    }

    /**
     * @param string|null $timezone
     * @return DateTime|null
     */
    public function getUpdatedAtDatetime(string $timezone = null): ?DateTime
    {
        if (!$this->isUpdated()) {
            return null;
        }

        $timezone = $timezone ?? Application::getConfig('application')->get('timezone', 'UTC');
        return new DateTime($this->getUpdatedAt(), new DateTimeZone($timezone));
    }

    /**
     * Get the timestamp of the created_at value
     *
     * @return int
     */
    public function getUpdatedAtTimestamp(): ?int
    {
        if (!$this->isUpdated()) {
            return null;
        }

        return strtotime($this->getUpdatedAt() . 'UTC');
    }

    /**
     * Sets the updated at value
     *
     * @param string $updatedAt - Pass the SQL's Y-m-d H:i:s or Y-m-d value, or leave undefined
     * @return $this
     */
    public function setUpdatedAt(string $updatedAt = null)
    {
        $this->updated_at = $updatedAt ?? gmdate('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Set the updated at date time by passing instance of updatedAt
     *
     * @param DateTime $updatedAt
     * @return $this
     */
    public function setUpdatedAtDateTime(DateTime $updatedAt)
    {
        $this->updated_at = $updatedAt->format('Y-m-d H:i:s');
        return $this;
    }

}
