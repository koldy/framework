<?php declare(strict_types = 1);

namespace Koldy\Log\Adapter;

use Koldy\Config\Exception as ConfigException;
use Koldy\Log\Exception;
use Koldy\Db\Query\Insert;
use Koldy\Db as KoldyDb;
use Koldy\Log;

/**
 * This log writer will insert your log messages into database.
 * For MySQL, we're recommending this table structure:
 *
 *    CREATE TABLE `log` (
 *      `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 *       `time` timestamp NOT NULL,
 *       `level` enum('debug','notice','sql','info','warning','error','exception') NOT NULL,
 *       `message` mediumtext CHARACTER SET utf16,
 *       PRIMARY KEY (`id`),
 *       KEY `time` (`time`),
 *       KEY `level` (`level`)
 *    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
 *
 * Of course, you can have your own table structure with totally different column
 * names, but then you need to define get_data_fn function in options for
 * this adapter.
 *
 * @link http://koldy.net/docs/log/db
 */
class Db extends AbstractLogAdapter
{

    /**
     * The flag if query is being inserted into database to prevent recursion
     *
     * @var boolean
     */
    private $inserting = false;

    /**
     * Construct the DB writer
     *
     * @param array $config
     *
     * @throws ConfigException
     */
    public function __construct(array $config)
    {
        $config['table'] = $config['table'] ?? 'log';
        $config['adapter'] = $config['adapter'] ?? null;

        if (isset($config['get_data_fn']) && !is_callable($config['get_data_fn'])) {
            throw new ConfigException('get_data_fn in DB writer options is not callable');
        }

        parent::__construct($config);
    }

    /**
     * Get array of field=>value to be inserted in log table
     *
     * @param string $level
     * @param string $message
     *
     * @throws Exception
     * @return array
     */
    protected function getFieldsData(string $level, string $message): array
    {
        if (isset($this->config['get_data_fn'])) {
            $data = call_user_func($this->config['get_data_fn'], $level, $message);

            if (!is_array($data)) {
                throw new Exception('DB driver config get_data_fn function must return an array; ' . gettype($data) . ' given');
            }

            return $data;
        }

        return array(
          'time' => time(),
          'level' => $level,
          'who' => Log::getWho(),
          'message' => $message
        );
    }

    /**
     * @param Log\Message $message
     *
     * @internal param string $level
     */
    public function logMessage(Log\Message $message): void
    {
        if ($this->inserting) {
            return;
        }

        $data = $this->getFieldsData($level, $message);

        if ($data !== false) {
            if (in_array($level, $this->config['log'])) {
                $this->inserting = true;

                $insert = new Insert($this->config['table'], $data, $this->config['adapter']);
                $insert->exec();
                // @todo test failures
            }

            $this->inserting = false;
        }
    }

}
