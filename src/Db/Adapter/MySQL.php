<?php declare(strict_types=1);

namespace Koldy\Db\Adapter;

use Koldy\Config\Exception as ConfigException;
use Koldy\Db\Adapter\Exception as AdapterException;
use PDO;
use PDOException;

class MySQL extends AbstractAdapter
{

	/**
	 * Connect to database
	 *
	 * @throws Exception
	 */
	public function connect(): void
	{
		try {
			$this->tryConnect();
		} catch (PDOException $firstException) {
			$this->pdo = null;

			// todo: implement backup connections
			throw new AdapterException($firstException->getMessage(), $firstException->getCode(), $firstException);
		}
	}

	/**
	 * Actually connect to database
	 */
	private function tryConnect(): void
	{
		$config = $this->getConfig();

		$pdoConfig = [
			PDO::ATTR_EMULATE_PREPARES => false,
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
			PDO::ATTR_PERSISTENT => $config['persistent'] ?? true,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
		];

		if (isset($config['adapter_options'])) {
			foreach ($config['adapter_options'] as $key => $value) {
				$pdoConfig[$key] = $value;
			}
		}

		$charset = $config['charset'] ?? 'utf8';

		if (!isset($config['socket'])) {
			// not a socket
			$port = $config['port'] ?? 3306;

			$this->pdo = new PDO("mysql:host={$config['host']};port={$port};dbname={$config['database']};charset={$charset}",
				$config['username'], $config['password'], $pdoConfig);
		} else {
			// the case with unix_socket
			$this->pdo = new PDO("mysql:unix_socket={$config['socket']};dbname={$config['database']};charset={$charset}",
				$config['username'], $config['password'], $pdoConfig);
		}
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
		}
	}

	/**
	 * @param array $config
	 *
	 * @throws ConfigException
	 */
	protected function checkConfig(array $config): void
	{
		foreach (['host', 'username', 'password', 'database', 'persistent'] as $key) {
			if (!isset($config[$key])) {
				$class = get_class($this);
				throw new ConfigException("Missing configuration key={$key} in {$class}");
			}
		}
	}

}
