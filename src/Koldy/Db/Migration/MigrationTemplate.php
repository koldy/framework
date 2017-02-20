<?php declare(strict_types = 1);

use Koldy\Db\Migration;

/**
 * Migration file for templateClassName. Take your time to write down() method!
 *
 * Migrations are not executed as SQL transactions, so if you put multiple SQL statements in up() method, be aware that
 * any statement can fail at any time, which is making down() method hard to write.
 *
 * Generated at templateClassGenerated
 */
class templateClassName extends Migration
{

    /**
     * Will be executed when migrating "up"
     */
    public function up(): void
    {
        //Db::query('ALTER TABLE users ADD is_active tinyint(1) NOT NULL DEFAULT 1')->exec();
    }

    /**
     * Will be executed when rolling back "down"
     *
     * Remember: down() is easier to write if up() doesn't contain a lot of SQL statements.
     */
    public function down(): void
    {
        //Db::query('ALTER TABLE users DROP COLUMN is_active')->exec();
    }

}
