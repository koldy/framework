<?php declare(strict_types=1);

namespace Koldy\Db;

abstract class Migration
{

    /**
     * Will be executed when migrating "up"
     *
     * @throws \Koldy\Db\Adapter\Exception
     * @throws \Koldy\Db\Query\Exception
     * @throws \Koldy\Db\Exception
     */
    abstract public function up();

    /**
     * Will be executed when rolling back "down"
     *
     * @throws \Koldy\Db\Adapter\Exception
     * @throws \Koldy\Db\Query\Exception
     * @throws \Koldy\Db\Exception
     */
    abstract public function down();

}
