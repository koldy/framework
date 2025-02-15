<?php declare(strict_types=1);

namespace Koldy\Db\Adapter;

use Koldy\Application;
use Koldy\Filesystem\Directory;
use PDO;
use Koldy\Db\Adapter\Exception as AdapterException;
use Koldy\Config\Exception as ConfigException;

class Sqlite extends AbstractAdapter
{

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
        } catch (\PDOException $firstException) {
            $this->pdo = null;

            // todo: implement backup connections
            throw new AdapterException($firstException->getMessage(), (int) $firstException->getCode(), $firstException);
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

        $this->pdo = new PDO('sqlite:' . $path);
        foreach ($pdoConfig as $key => $value) {
            $this->pdo->setAttribute($key, $value);
        }

        // create needed function(s)
        $this->pdo->sqliteCreateFunction('ILIKE', function ($mask, $value) {
            $mask = str_replace(['%', '_'], ['.*?', '.'], preg_quote($mask, '/'));
            $mask = "/^$mask$/ui";
            return preg_match($mask, $value);
        }, 2);
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

}
