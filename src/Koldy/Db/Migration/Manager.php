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

                // simply, take all records from database, check the last one and remove all $files before the last in database

                /** @var KoldyMigration[] $executedMigrations */
                $executedMigrations = $class::all('script_timestamp', 'ASC');
                $executedMigrationsCount = count($executedMigrations);

                $olderMigrationsCount = 0;

                if ($executedMigrationsCount > 0) {
                    $s = $executedMigrationsCount != 1 ? 's' : '';
                    Log::notice("{$executedMigrationsCount} migration{$s} was executed before till now");

                    $lastMigration = $executedMigrations[count($executedMigrations) - 1];
                    $lastMigrationTimestamp = $lastMigration->getScriptTimestamp();

                    $filesToRemove = [];
                    foreach ($files as $file => $path) {
                        $parts = explode('_', $file);
                        if (count($parts) != 2) {
                            throw new Exception("Invalid file name found in migrations: {$file}; please create migration files using: \"./koldy create-migration migration-name\"");
                        }

                        $timestamp = $parts[0];

                        if ($timestamp <= $lastMigrationTimestamp) {
                            if ($force) {
                                // if $force is TRUE, then we should not remove anything from stack; we'll execute it all
                                $olderMigrationsCount++;
                            } else {
                                // if $force is FALSE, then we'll remove all "older" migrations from stack so it won't be executed
                                $filesToRemove[] = $file;
                            }
                        }
                    }

                    foreach ($filesToRemove as $file) {
                        unset($files[$file]);
                    }

                    unset($filesToRemove);
                }

                $filesCount = count($files);
                $s = $filesCount != 1 ? 's' : '';

                if ($force && $olderMigrationsCount > 0) {
                    $s2 = $olderMigrationsCount != 1 ? 's' : '';
                    Log::info("{$filesCount} migration{$s} will be executed now, INCLUDING {$olderMigrationsCount} older migration{$s2}");
                } else {
                    Log::info("{$filesCount} migration{$s} will be executed now");
                }

                foreach ($files as $file => $path) {
                    $parts = explode('_', $file);
                    if (count($parts) != 2) {
                        throw new Exception("Invalid file name found in migrations: {$file}; please create migration files using: \"./koldy create-migration migration-name\"");
                    }

                    $timestamp = (int)$parts[0];
                    $migrationClassName = 'Migration_' . str_replace('.php', '', $file);
                    include_once $path;

                    /** @var \Koldy\Db\Migration $migration */
                    $migration = new $migrationClassName();
                    $startTime = microtime(true);
                    Log::info("Starting to EXECUTE MIGRATION script {$timestamp}_{$migrationClassName}");
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