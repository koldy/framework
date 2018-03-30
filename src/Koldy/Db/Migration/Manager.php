<?php declare(strict_types=1);

namespace Koldy\Db\Migration;

use Koldy\Application;
use Koldy\Db\Adapter\{
  MySQL, PostgreSQL, Sqlite
};
use Koldy\Db\Migration;
use Koldy\Db\Model;
use Koldy\Db\Exception as DbException;
use Koldy\Filesystem\Directory;
use Koldy\Log;
use Koldy\Util;

class Manager
{

    /**
     * Create migration with given name
     *
     * @param string $name
     *
     * @throws Application\Exception
     * @throws \Koldy\Filesystem\Exception
     */
    public static function createMigration(string $name): void
    {
        $directory = Application::getApplicationPath('migrations');

        if (!is_dir($directory)) {
            Directory::mkdir($directory, 0755);
        }

        $timestamp = time();
        $className = Util::camelCase($name, null, false);
        $phpClassName = "Migration_{$timestamp}_{$className}";
        $fileName = "{$timestamp}_{$className}.php";
        $fullPath = Application::getApplicationPath("migrations/{$fileName}");

        $migrationFileContent = file_get_contents(__DIR__ . '/MigrationTemplate.php');
        $migrationFileContent = str_replace('templateClassName', $phpClassName, $migrationFileContent);
        $migrationFileContent = str_replace('templateClassGenerated', date('r'), $migrationFileContent);

        if (file_put_contents($fullPath, $migrationFileContent) === false) {
            throw new Application\Exception("Unable to write to migration file on path={$fullPath}");
        }

        Log::info("Created DB migration with name: {$name}");
        Log::info("You may now open application/migrations/{$fileName} and add your migration code");
    }

    /**
     * @return string
     * @throws Exception
     */
    private static function getMigrationClass(): string
    {
        if (is_file('KoldyMigration.php')) {
            require_once 'KoldyMigration.php';

            /** @var Model $class */
            $class = '\KoldyMigration';

            if (!class_exists($class, false)) {
                $class = KoldyMigration::class;
            }
        } else {
            $class = KoldyMigration::class;
        }

        $instance = new $class();
        if (!($instance instanceof Model)) {
            $theClass = get_class($instance);
            throw new Exception("Class {$theClass} is not an instance of \\Koldy\\Db\\Model, can not proceed");
        }

        return $class;
    }

    /**
     * Start executing migration scripts
     *
     * @param bool $force - Set true if you want to run "older" migrations that weren't ran before
     *
     * @throws DbException
     * @throws Exception
     * @throws \Koldy\Db\Query\Exception
     * @throws \Koldy\Exception
     * @throws \Koldy\Filesystem\Exception
     */
    public static function migrate(bool $force = false): void
    {
        $migrationsPath = Application::getApplicationPath('migrations');
        $files = Directory::readFiles($migrationsPath, '/\.php$/');

        if (count($files) == 0) {
            Log::info("There are no migrations to execute in {$migrationsPath}");
        } else {
            /** @var KoldyMigration $class */
            $class = static::getMigrationClass();
            $tableName = $class::getTableName();
            $adapter = $class::getAdapter();

            try {

                $class::count();

            } catch (DbException $e) {
                // table probably doesn't exists, let's create it
                try {
                    $sql = '';

                    if ($adapter instanceof MySQL) {
                        $sql .= "CREATE TABLE {$tableName} (\n";
                        $sql .= "  id int unsigned not null auto_increment,\n";
                        $sql .= "  script varchar(255) not null,\n";
                        $sql .= "  script_timestamp int not null,\n";
                        $sql .= "  script_executed_at datetime not null,\n";
                        $sql .= "  script_execution_duration int not null,\n";
                        $sql .= "  PRIMARY KEY(id),\n";
                        $sql .= "  INDEX (script_timestamp)\n";
                        $sql .= ")Engine=InnoDB";
                        $adapter->query($sql)->exec();

                    } else if ($adapter instanceof PostgreSQL) {
                        $sql .= "CREATE TABLE {$tableName} (\n";
                        $sql .= "  id serial,\n";
                        $sql .= "  script varchar(255) not null,\n";
                        $sql .= "  script_timestamp int not null,\n";
                        $sql .= "  script_executed_at timestamp not null,\n";
                        $sql .= "  script_execution_duration int not null,\n";
                        $sql .= "  PRIMARY KEY(id)\n";
                        $sql .= ")\n";
                        $adapter->query($sql)->exec();

                        $sql = "CREATE INDEX i_{$tableName}_script_timestamp ON {$tableName} (script_timestamp)";
                        $adapter->query($sql)->exec();

                    } else if ($adapter instanceof Sqlite) {
                        $sql .= "CREATE TABLE {$tableName} (\n";
                        $sql .= "  id integer primary key autoincrement,\n";
                        $sql .= "  script char(255) not null,\n";
                        $sql .= "  script_timestamp integer not null,\n";
                        $sql .= "  script_executed_at char(20) not null,\n";
                        $sql .= "  script_execution_duration integer not null\n";
                        $sql .= ")";
                        $adapter->query($sql)->exec();

                    }

                } catch (DbException $e) {
                    Log::error("Can not create Koldy migrations table in database, using query:\n{$sql}");
                    $adapter->query("DROP TABLE IF EXISTS {$tableName}")->exec();
                    throw $e;
                }
            }

            $files = array_flip($files);

            if (count($files) == 0) {

                Log::info('Nothing to migrate; no PHP files in migrations folder');

            } else {
                ksort($files);

                /** @var KoldyMigration[] $executedMigrations */
                $executedMigrations = [];
                $lastMigration = null;

                foreach ($class::all('script_timestamp', 'ASC') as $migration) {
                    /** @var KoldyMigration $migration */
                    $executedMigrations[$migration->getScript()] = $migration;
                    $lastMigration = $migration;
                }

                // simply iterate through all files on filesystem and create lists to-do and wasnt-executed

                $filesToDo = [];
                $notExecutedFilesBefore = [];

                foreach ($files as $file => $path) {
                    $script = substr($file, 0, -4);
                    $parts = explode('_', $file);

                    if (count($parts) != 2) {
                        throw new Exception("Invalid file name found in migrations: {$file}; please create migration files using: \"./koldy create-migration migration-name\"");
                    }

                    $timestamp = (int)$parts[0];

                    if (!array_key_exists($script, $executedMigrations)) {
                        // it wasn't executed yet, let's decide what to do

                        // should we add it to to-do list?
                        if ($lastMigration === null) {
                            // there is no last migration, se we must add file for sure
                            $filesToDo[$file] = $path;
                        } else {

                            $lastMigrationTimestamp = $lastMigration->getScriptTimestamp();

                            if ($timestamp < $lastMigrationTimestamp) {
                                $notExecutedFilesBefore[] = $script;

                                // should we add this as well?
                                if ($force) {
                                    $filesToDo[$file] = $path;
                                }
                            } else {
                                // otherwise, timestamp is newer than last executed migration
                                $filesToDo[$file] = $path;
                            }
                        }
                    }
                }

                $wasExecutedBeforeCount = count($executedMigrations);
                $wasntExecutedBeforeCount = count($notExecutedFilesBefore);
                $filesToExecuteCount = count($filesToDo);

                $s = $wasExecutedBeforeCount != 1 ? 's' : '';
                Log::notice("{$wasExecutedBeforeCount} migration{$s} WAS executed before");

                if ($wasntExecutedBeforeCount > 0) {
                    $s = $wasntExecutedBeforeCount != 1 ? 's' : '';

                    if ($force) {
                        Log::info("{$wasntExecutedBeforeCount} migration{$s} that had to be executed before WILL BE executed NOW because migration script is running with --force flag");
                    } else {
                        Log::info("{$wasntExecutedBeforeCount} migration{$s} WILL NOT be executed because those files are not in the right order and script is not forced (no --force flag). Not-executed migration{$s} from before are:", implode(', ', $notExecutedFilesBefore));
                    }
                }

                $s = $filesToExecuteCount != 1 ? 's' : '';
                Log::notice("{$filesToExecuteCount} detected migration{$s} WILL BE executed now");

                foreach ($filesToDo as $file => $path) {
                    $script = substr($file, 0, -4);
                    $parts = explode('_', $file);

                    if (count($parts) != 2) {
                        throw new Exception("Invalid file name found in migrations: {$file}; please create migration files using: \"./koldy create-migration migration-name\"");
                    }

                    $timestamp = (int)$parts[0];
                    $migrationClassName = "Migration_{$script}";
                    include_once $path;

                    /** @var \Koldy\Db\Migration $migration */
                    $migration = new $migrationClassName();
                    $startTime = microtime(true);
                    Log::info("Starting to EXECUTE MIGRATION script {$script}");
                    $dashes = str_repeat('-', 50);
                    Log::info($dashes);

                    /*********************************/
                    /**/                           /**/
                    /**/     $migration->up();     /**/
                    /**/                           /**/
                    /*********************************/

                    Log::info($dashes);
                    $endTime = microtime(true);

                    $scriptExecutionDuration = $endTime - $startTime;

                    /** @var KoldyMigration $newMigrationEntry */
                    $newMigrationEntry = $class::create([
                      'script' => str_replace('.php', '', $file),
                      'script_timestamp' => $timestamp,
                      'script_executed_at' => gmdate('Y-m-d H:i:s'),
                      'script_execution_duration' => round($scriptExecutionDuration)
                    ]);

                    Log::info("Executed migration {$migrationClassName} in {$scriptExecutionDuration}s and written in {$tableName} (#{$newMigrationEntry->id})");
                }

                Log::info('All done; go back to work now');
            }
        }
    }

    /**
     * Roll back one or ore migration(s)
     *
     * @param int $stepsDown
     *
     * @throws Exception
     * @throws \Koldy\Db\Query\Exception
     */
    public static function rollBack(int $stepsDown = 1): void
    {
        if ($stepsDown < 1) {
            throw new \InvalidArgumentException("Invalid \$stepsDown argument, expected positive int, got {$stepsDown}");
        }

        /** @var KoldyMigration $class */
        $class = static::getMigrationClass();

        Log::debug("Will do {$stepsDown} step(s) rollback");
        $rollbackMigrations = $class::select()
          ->orderBy('script_timestamp', 'DESC')
          ->orderBy('script', 'DESC')
          ->limit(0, $stepsDown)
          ->fetchAll();

        for ($i = 0, $count = count($rollbackMigrations); $i < $stepsDown && $i < $count; $i++) {
            $mgr = $rollbackMigrations[$i];
            $id = $mgr['id'];
            $script = $mgr['script'];
            $className = 'Migration_' . $script;
            $filePath = Application::getApplicationPath("migrations/{$script}.php");

            if (!is_file($filePath)) {
                throw new Exception("Can not execute rollback migration, script not found on {$filePath}");
            }

            include_once $filePath;

            /** @var Migration $migration */
            $migration = new $className();

            Log::info("Starting to EXECUTE ROLLBACK script {$script}");
            $dashes = str_repeat('-', 50);
            $startTime = microtime(true);
            Log::debug($dashes);

            /***********************************/
            /**/                             /**/
            /**/     $migration->down();     /**/
            /**/                             /**/
            /***********************************/

            Log::debug($dashes);
            $endTime = microtime(true);

            $scriptExecutionDuration = $endTime - $startTime;
            $class::delete($id);

            Log::info("Executed rollback {$className} in {$scriptExecutionDuration}s and removed from migration table {$class::getTableName()} (#{$id})");
        }
    }

}
