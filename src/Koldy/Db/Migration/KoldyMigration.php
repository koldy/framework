<?php declare(strict_types=1);

namespace Koldy\Db\Migration;

use Koldy\Application;
use Koldy\Db\Model;

/**
 * Class KoldyMigration
 *
 * If you want to override this class, just create "KoldyMigration" class anywhere in your project and extend it
 * with Koldy\Db\Model, after which you can redefine connection and table name.
 *
 * @package Koldy\Db\Migration
 */
class KoldyMigration extends Model {

    protected static $table = 'koldy_migration';

    /**
     * If you define koldy_migration database adapter, then migrations table will be stored there
     *
     * @return null|string
     */
    public static function getAdapterConnection(): ?string
    {
        return Application::getConfig('database')->has('koldy_migration') ? 'koldy_migration' : null;
    }

}