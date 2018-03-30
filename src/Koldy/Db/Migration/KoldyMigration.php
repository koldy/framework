<?php declare(strict_types=1);

namespace Koldy\Db\Migration;

use Koldy\Db;
use Koldy\Db\Model;
use Koldy\Db\ModelTraits\Id;

/**
 * Class KoldyMigration
 *
 * If you want to override this class, just create "KoldyMigration" class anywhere in your project and extend it
 * with Koldy\Db\Model, after which you can redefine connection and table name.
 *
 * @package Koldy\Db\Migration
 *
 * @property int id
 * @property string script
 * @property int script_timestamp
 * @property string script_executed_at
 * @property int script_execution_duration
 */
class KoldyMigration extends Model
{

    protected static $table = 'koldy_migration';

    /**
     * If you define koldy_migration database adapter, then migrations table will be stored there
     *
     * @return null|string
     * @throws \Koldy\Exception
     */
    public static function getAdapterConnection(): ?string
    {
        return Db::getConfig()->has('koldy_migration') ? 'koldy_migration' : null;
    }

    use Id;

    /**
     * Get the script (and class) name
     *
     * @return string
     */
    public function getScript(): string
    {
        return $this->script;
    }

    /**
     * Get the timestamp of the script. This is the timestamp of when script was generated
     *
     * @return int
     */
    public function getScriptTimestamp(): int
    {
        return (int)$this->script_timestamp;
    }

    /**
     * Get yyyy-mm-dd of when script was executed
     *
     * @return string
     */
    public function getScriptExecutedAt(): string
    {
        return $this->script_executed_at;
    }

    /**
     * Get how long did it take to execute migration up() method in seconds
     *
     * @return int
     */
    public function getScriptExecutionDuration(): int
    {
        return (int)$this->script_execution_duration;
    }

}