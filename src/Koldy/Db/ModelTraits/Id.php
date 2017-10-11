<?php declare(strict_types=1);

namespace Koldy\Db\ModelTraits;

/**
 * Trait for models that has the "id" column which is integer
 * @package Koldy\Db\ModelTraits
 *
 * @property int id
 */
trait Id {

    /**
     * @return int
     */
    public function getId(): int
    {
        return (int)$this->id;
    }

}
