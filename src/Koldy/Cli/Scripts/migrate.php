<?php declare(strict_types = 1);

use Koldy\Application;
use Koldy\Cli\Exception as CliException;
use Koldy\Filesystem\Directory;
use Koldy\Db\Migration\KoldyMigration;
use Koldy\Db\Adapter\{
  MySQL, PostgreSQL, Sqlite
};
use Koldy\Db\{
  Exception, Model
};
use Koldy\Log;

/** @var Model $class */
//$class = '\KoldyMigration';
//if (!class_exists($class)) {
$class = KoldyMigration::class;
//}

$instance = new $class();
if (!($instance instanceof Model)) {
    $theClass = get_class($class);
    throw new Exception("Class {$theClass} is not an instance of \\Koldy\\Db\\Model, can not proceed");
}

$tableName = $class::getTableName();
$adapter = $class::getAdapter();

try {
    $class::count();
} catch (Exception $e) {
    // table probably doesn't exists, let's create it
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
    } else if ($adapter instanceof PostgreSQL) {
        $sql .= "CREATE TABLE {$tableName} (\n";
        $sql .= "  id serial,\n";
        $sql .= "  script varchar(255) not null,\n";
        $sql .= "  script_timestamp int not null,\n";
        $sql .= "  script_executed_at datetime not null,\n";
        $sql .= "  script_execution_duration int not null,\n";
        $sql .= "  PRIMARY KEY(id),\n";
        $sql .= "  INDEX (script_timestamp)\n";
        $sql .= ")";
    } else if ($adapter instanceof Sqlite) {
        $sql .= "CREATE TABLE {$tableName} (\n";
        $sql .= "  id integer priamry key autoincrement,\n";
        $sql .= "  script char(255) not null,\n";
        $sql .= "  script_timestamp integer not null,\n";
        $sql .= "  script_executed_at char(20) not null,\n";
        $sql .= "  script_execution_duration integer not null\n";
        $sql .= ")";
    }

    try {
        $adapter->query($sql)->exec();
    } catch (Exception $e) {
        Log::error("Can not create Koldy migrations table in database, using query:\n{$sql}");
        throw $e;
    }
}

$files = Directory::readFiles(Application::getApplicationPath('migrations'), '/\.php$/');
$files = array_flip($files);

if (count($files) == 0) {
    Log::info('Nothing to migrate; no PHP files in migrations folder');
} else {
    ksort($files);

    // simply, take all records from database, check the last one and remove all $files before the last in database
    $executedMigrations = $class::all('script_timestamp', 'ASC');
    $executedMigrationsCount = count($executedMigrations);

    if ($executedMigrationsCount > 0) {
        $s = $executedMigrationsCount != 1 ? 's' : '';
        Log::debug("{$executedMigrationsCount} migration{$s} was already executed");

        $lastMigration = $executedMigrations[count($executedMigrations) - 1];
        $lastMigrationTimestamp = $lastMigration->script_timestamp;

        $filesToRemove = [];
        foreach ($files as $file => $path) {
            $parts = explode('_', $file);
            if (count($parts) != 2) {
                throw new CliException("Invalid file name found in migrations: {$file}; please create migration files using: php public/index.php koldy create-migration migration-name");
            }

            $timestamp = $parts[0];

            if ($timestamp <= $lastMigrationTimestamp) {
                $filesToRemove[] = $file;
            }
        }

        foreach ($filesToRemove as $file) {
            unset($files[$file]);
        }

        unset($filesToRemove);
    }

    $filesCount = count($files);
    $s = $filesCount != 1 ? 's' : '';
    Log::debug("{$filesCount} migration{$s} will be executed now");

    foreach ($files as $file => $path) {
        $parts = explode('_', $file);
        if (count($parts) != 2) {
            throw new CliException("Invalid file name found in migrations: {$file}; please create migration files using: php public/index.php koldy create-migration migration-name");
        }

        $timestamp = (int) $parts[0];
        $migrationClassName = str_replace('.php', '', $parts[1]);
        require_once $path;

        /** @var Koldy\Db\Migration $migrationClassInstance */
        $migrationClassInstance = new $migrationClassName();
        $startTime = microtime(true);
        Log::info("Starting to execute migration script {$timestamp}_{$migrationClassName}");
        $migrationClassInstance->up();
        $endTime = microtime(true);

        $scriptExecutionDuration = $endTime - $startTime;

        /** @var KoldyMigration $newMigrationEntry */
        $newMigrationEntry = $class::create([
            'script' => str_replace('.php', '', $file),
            'script_timestamp' => $timestamp,
            'script_executed_at' => gmdate('Y-m-d H:i:s'),
            'script_execution_duration' => round($scriptExecutionDuration)
        ]);

        Log::info("Executed migration script {$timestamp}_{$migrationClassName} in {$scriptExecutionDuration}s and written in {$tableName} (#{$newMigrationEntry->id})");
    }

    Log::info('Migration(s) done; go back to work now');
}