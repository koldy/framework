<?php declare(strict_types = 1);

use Koldy\Application;
use Koldy\Cli;
use Koldy\Cli\Exception;
use Koldy\Db\Migration\Manager;
use Koldy\Filesystem\Directory;
use Koldy\Log;
use Koldy\Util;

if (!Cli::hasParameterOnPosition(3)) {
    throw new Exception('Koldy create-migration script doesn\'t have name passed to script; e.g. use: php public/index.php koldy create-migration add-column-is-user-active');
}

$name = Cli::getParameterOnPosition(3);
$directory = Application::getApplicationPath('migrations');

if (!is_dir($directory)) {
    Directory::mkdir($directory, 0755);
}

$timestamp = time();
$className = Util::camelCase($name, null, false);
$fileName = "{$timestamp}_{$className}.php";
$fullPath = Application::getApplicationPath("migrations/{$fileName}");

$migrationFileContent = file_get_contents(__DIR__ . '/../../Db/Migration/MigrationTemplate.php');
$migrationFileContent = str_replace('templateClassName', $className, $migrationFileContent);
$migrationFileContent = str_replace('templateClassGenerated', date('r'), $migrationFileContent);

if (file_put_contents($fullPath, $migrationFileContent) === false) {
    throw new Application\Exception("Unable to write to migration file on path={$fullPath}");
}

Log::info("Created DB migration with name: {$name}");
Log::info("You may now open application/migrations/{$fileName} and add your migration code");
