<?php

declare(strict_types=1);

namespace Tests\DbUnit\Support;

use Koldy\Application;
use Koldy\Db;
use PHPUnit\Framework\TestCase;

/**
 * Base class for every dbunit test. Registers the database connections from tests/dbunit/config.php and makes sure
 * adapters and config don't leak between test classes.
 *
 * Tests in this suite REQUIRE running database servers (see tests/dbunit/compose.yaml); a connection failure is a test
 * error on purpose, never a skip.
 */
abstract class DbUnitTestCase extends TestCase
{

	public static function setUpBeforeClass(): void
	{
		$_SERVER['SCRIPT_FILENAME'] = __FILE__;

		// always start from a clean slate: configs are cached per name and adapters are static
		Db::removeAdapters();
		Application::removeConfig('database');

		Application::useConfig([
			'site_url' => 'http://localhost',
			'env' => Application::TEST,
			'key' => 'DbUnitTestKey12345678901',
			'timezone' => 'UTC',
			'paths' => [
				'application' => __DIR__ . '/',
				'storage' => __DIR__ . '/',
			],
			'configs' => [
				'database' => require __DIR__ . '/../config.php',
			],
		]);
	}

	public static function tearDownAfterClass(): void
	{
		Db::removeAdapters();
		Application::removeConfig('database');
	}

}
