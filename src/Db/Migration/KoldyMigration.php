<?php declare(strict_types=1);

namespace Koldy\Db\Migration;

use Koldy\Db;
use Koldy\Db\Model;

/**
 * Class KoldyMigration
 *
 * If you want to override this class, just create "KoldyMigration" class anywhere in your project and extend it
 * with Koldy\Db\Model, after which you can redefine connection and table name.
 *
 * @package Koldy\Db\Migration
 *
 * @property int $id
 * @property string $script
 * @property int $script_timestamp
 * @property string $script_executed_at
 * @property int $script_execution_duration
 */
class KoldyMigration extends Model
{

    protected static string | null $table = 'koldy_migration';

    /**
     * If you define koldy_migration database adapter, then migration table will be stored there
     *
     * @return null|string
     * @throws \Koldy\Exception
     */
    public static function getAdapterConnection(): ?string
    {
        return Db::getConfig()->has('koldy_migration') ? 'koldy_migration' : null;
    }

	/**
	 * Get the ID or null.
	 *
	 * @return int|null
	 */
	public function getId(): ?int
	{
		if ($this->id === null) {
			return null;
		}

		return (int)$this->id;
	}

	/**
	 * Is ID set or not
	 *
	 * @return bool
	 */
	public function hasId(): bool
	{
		return $this->id !== null;
	}

	/**
	 * Set the ID
	 *
	 * @param int|null $id
	 */
	public function setId(?int $id): void
	{
		$this->id = $id;
	}

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
