<?php declare(strict_types = 1);

use Koldy\Cli;
use Koldy\Cli\Exception as CliException;
use Koldy\Db\Migration\Manager;

if (!Cli::hasParameterOnPosition(3)) {
    throw new CliException('Koldy create-migration script doesn\'t have name passed to script; e.g. use: php public/index.php koldy create-migration add-column-is-user-active');
}

$name = trim(Cli::getParameterOnPosition(3));

if (strlen($name) == 0) {
    throw new CliException('Koldy create-migration script doesn\'t have name passed to script; e.g. use: php public/index.php koldy create-migration add-column-is-user-active');
}

Manager::createMigration($name);