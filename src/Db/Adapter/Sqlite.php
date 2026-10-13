<?php declare(strict_types=1);

namespace Koldy\Db\Adapter;

use Koldy\Application;
use Koldy\Config\Exception as ConfigException;
use Koldy\Db\Adapter\Exception as AdapterException;
use Koldy\Filesystem\Directory;
use PDO;
use PDOException;

class Sqlite extends AbstractAdapter
{

	/**
	 * Cached result of supportsReturning(); null until the library version was checked on an open connection
	 *
	 * @var bool|null
	 */
	private bool|null $returningSupported = null;

	/**
	 * Connect to database
	 *
	 * @throws ConfigException
	 * @throws Exception
	 * @throws \Koldy\Exception
	 * @throws \Koldy\Filesystem\Exception
	 */
	public function connect(): void
	{
		try {
			$this->tryConnect();
			// @phpstan-ignore-next-line
		} catch (PDOException $firstException) {
			$this->pdo = null;

			// todo: implement backup connections
			throw new AdapterException($firstException->getMessage(), (int)$firstException->getCode(), $firstException);
		}
	}

	/**
	 * Actually connect to database
	 *
	 * @throws ConfigException
	 * @throws \Koldy\Exception
	 * @throws \Koldy\Filesystem\Exception
	 */
	private function tryConnect(): void
	{
		$config = $this->getConfig();

		$pdoConfig = [
			PDO::ATTR_EMULATE_PREPARES => false,
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
		];

		if (isset($config['adapter_options'])) {
			foreach ($config['adapter_options'] as $key => $value) {
				$pdoConfig[$key] = $value;
			}
		}

		if (isset($config['path'])) {
			$path = $config['path'];
			if (str_starts_with($path, 'storage:')) {
				$path = Application::getStoragePath(substr($path, 8));
			}
		} else {
			$path = Application::getStoragePath('data/database.sqlite');
		}

		$directory = dirname($path);
		if (!is_dir($directory)) {
			Directory::mkdir($directory, 0755);
		}

		$dsn = 'sqlite:' . $path;

		// PDO::sqliteCreateFunction() is deprecated as of PHP 8.5 in favour of Pdo\Sqlite::createFunction(). The
		// driver-specific Pdo\Sqlite instance comes from PDO::connect(), which exists since PHP 8.4.
		// Both calls are suppressed for static analysis, which targets PHP 8.3 where neither exists; 8.3 itself keeps
		// the old, not yet deprecated path.
		if (PHP_VERSION_ID >= 80400) {
			// @phpstan-ignore-next-line
			$this->pdo = PDO::connect($dsn);
		} else {
			$this->pdo = new PDO($dsn);
		}

		foreach ($pdoConfig as $key => $value) {
			$this->pdo->setAttribute($key, $value);
		}

		// create needed function(s)
		$ilike = static function ($mask, $value) {
			$mask = str_replace(['%', '_'], ['.*?', '.'], preg_quote($mask, '/'));
			$mask = "/^$mask$/ui";
			return preg_match($mask, $value);
		};

		if (PHP_VERSION_ID >= 80400) {
			// @phpstan-ignore-next-line
			$this->pdo->createFunction('ILIKE', $ilike, 2);
		} else {
			$this->pdo->sqliteCreateFunction('ILIKE', $ilike, 2);
		}
	}

	/**
	 * SQLite supports INSERT ... RETURNING since 3.35.0 (March 2021). PHP links against the system libsqlite, which can
	 * be older than that on long-term-support distributions, so the version is checked on the open connection.
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \Koldy\Exception
	 */
	public function supportsReturning(): bool
	{
		if ($this->returningSupported === null) {
			$libVersion = (string)$this->getPDO()->getAttribute(PDO::ATTR_SERVER_VERSION);
			$this->returningSupported = version_compare($libVersion, '3.35.0', '>=');
		}

		return $this->returningSupported;
	}

	/**
	 * Close connection to database if it was opened
	 */
	public function close(): void
	{
		if ($this->pdo instanceof PDO) {

			if ($this->stmt !== null) {
				$this->stmt->closeCursor();
			}

			$this->stmt = null;
			$this->pdo = null;
			$this->returningSupported = null;

		}
	}

	/**
	 * @param array $config
	 *
	 * @throws ConfigException
	 */
	protected function checkConfig(array $config): void
	{
		if (array_key_exists('path', $config)) {
			if ($config['path'] === null) {
				throw new ConfigException('Path can not be null in Sqlite config');
			}
		}
	}

}
