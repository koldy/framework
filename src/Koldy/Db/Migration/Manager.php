<?php declare(strict_types = 1);

namespace Koldy\Db\Migration;

use Koldy\Application;
use Koldy\Db\Adapter\MySQL;
use Koldy\Db\Adapter\PostgreSQL;
use Koldy\Db\Adapter\Sqlite;
use Koldy\Db\Exception;
use Koldy\Db\Model;
use Koldy\Filesystem\Directory;
use Koldy\Log;
use Koldy\Util;

class Manager
{

    /**
     * Roll back one or ore migration(s)
     *
     * @param int $steps
     */
    public static function rollBack(int $steps = 1): void
    {

    }
}