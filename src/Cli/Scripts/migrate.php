<?php declare(strict_types=1);

use Koldy\Cli;
use Koldy\Db\Migration\Manager;

Manager::migrate(Cli::hasParameter('force'));