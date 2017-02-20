<?php declare(strict_types=1);

namespace Koldy\Db\Migration;

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

}