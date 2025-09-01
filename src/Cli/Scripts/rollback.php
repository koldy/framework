<?php declare(strict_types=1);

use Koldy\Cli;
use Koldy\Cli\Exception as CliException;
use Koldy\Db\Migration\Manager;

if (Cli::hasParameterOnPosition(3)) {
	$stepsBack = Cli::getParameterOnPosition(3);

	if (!is_numeric($stepsBack)) {
		throw new CliException('If you\'re passing number of steps to rollback, then it has to be positive integer; e.g. use: ./koldy rollback 5');
	}

	$stepsBack = (int)$stepsBack;

	if ($stepsBack < 1) {
		throw new CliException('If you\'re passing number of steps to rollback, then it has to be positive integer, at least 1; e.g. use: ./koldy rollback 5');
	}
} else {
	$stepsBack = 1;
}

Manager::rollBack($stepsBack);
