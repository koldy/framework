<?php declare(strict_types=1);

namespace Koldy\Db;

abstract class Migration
{

    /**
     * Will be executed when migrating "up"
     */
    abstract public function up();

    /**
     * Will be executed when rolling back "down"
     */
    abstract public function down();

}