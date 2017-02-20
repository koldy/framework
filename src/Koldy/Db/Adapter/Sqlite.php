<?php declare(strict_types = 1);

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
     */
    public function connect(): void
    {
        try {
            $this->tryConnect();
        } catch (\PDOException $firstException) {
            $this->pdo = null;

            // todo: implement backup connections
            throw new AdapterException($firstException->getMessage(), (int) $firstException->getCode(), $firstException);
        }
    }

    /**
     * Actually connect to database
     */
    private function tryConnect(): void
    {
        $config = $this->getConfig();

        $pdoConfig = array(
          PDO::ATTR_EMULATE_PREPARES => false,
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        );

        if (isset($config['adapter_options'])) {
            foreach ($config['adapter_options'] as $key => $value) {
                $pdoConfig[$key] = $value;
            }
        }

        if (isset($config['path'])) {
            $path = $config['path'];
            if (substr($path, 0, 8) == 'storage:') {
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
            $mask = str_replace(array('%', '_'), array('.*?', '.'), preg_quote($mask, '/'));
            $mask = "/^$mask$/ui";
            return preg_match($mask, $value);
        }, 2);
    }

    /**
     * Close connection to database if it was opened
     *
     * @throws Exception
     */
    public function close(): void
    {
        if ($this->pdo instanceof PDO) {

            if ($this->stmt !== null) {
                $this->stmt->closeCursor();
            }

            $this->stmt = null;
            $this->pdo = null;

        } else if ($this->pdo === null) {
            // to nothing
        } else {
            throw new AdapterException('Unable to close database connection when PDO handler is not an instance of PDO');
        }
    }

}