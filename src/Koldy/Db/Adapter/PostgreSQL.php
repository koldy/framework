<?php declare(strict_types=1);

namespace Koldy\Db\Adapter;

use Koldy\Log;
use PDO;
use Koldy\Db\Adapter\Exception as AdapterException;
use Koldy\Config\Exception as ConfigException;
use PDOException;

class PostgreSQL extends AbstractAdapter
{

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
            throw new AdapterException($firstException->getMessage(), (int) $firstException->getCode(), $firstException);
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
          PDO::ATTR_PERSISTENT => $config['persistent'] ?? true,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];

        if (isset($config['adapter_options'])) {
            foreach ($config['adapter_options'] as $key => $value) {
                $pdoConfig[$key] = $value;
            }
        }

        if (!isset($config['socket'])) {
            // not a socket
            $port = $config['port'] ?? 5432;

            $this->pdo = new PDO("pgsql:host={$config['host']};port={$port};dbname={$config['database']}", $config['username'], $config['password'], $pdoConfig);
        } else {
            // the case with unix_socket
            $this->pdo = new PDO("pgsql:dbname={$config['database']}", $config['username'], $config['password'], $pdoConfig);
        }

        if (isset($config['schema'])) {
            $schemaSql = 'SET search_path TO ' . $config['schema'];
            $this->pdo->exec($schemaSql);
            Log::sql($schemaSql);
        }
    }

    /**
     * Close connection to database if it was opened
     */
    public function close(): void
    {
        if ($this->pdo instanceof PDO) {
	        $this->stmt?->closeCursor();
            $this->stmt = null;
            $this->pdo = null;
        }
    }

    /**
     * Helper for Postgres determining if there's a boolean value in database. This is useful because PDO on Postgres
     * returns string for boolean values.
     *
     * If you pass null as parameter, you'll get null back.
     *
     * @param null|string $input
     *
     * @return bool|null
     * @throws LanguageException
     */
    public static function isBoolTrue(?string $input): ?bool
    {
        if ($input === null) {
            return null;
        }

        $input = strtolower($input);

        if ($input === 'true' || $input === '1') {
            return true;

        } else if ($input === 'false' || $input === '0' || $input === '') {
            return false;
        }

        throw new LanguageException('Unable to determine if given $input is boolean');
    }

}
