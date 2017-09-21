<?php declare(strict_types=1);

namespace Koldy\Log\Adapter;

use Koldy\Config\Exception as ConfigException;
use Koldy\Log\Exception;
use Koldy\Db\Query\Insert;
use Koldy\Log;
use Koldy\Log\Message;

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

    private const FN_CONFIG_KEY = 'get_insert_fn';

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

        if (isset($config[self::FN_CONFIG_KEY]) && !is_callable($config[self::FN_CONFIG_KEY])) {
            throw new ConfigException(self::FN_CONFIG_KEY . ' in DB log adapter options is not callable');
        }

        parent::__construct($config);
    }

    /**
     * @param Message $message
     *
     * @throws Exception
     */
    public function logMessage(Message $message): void
    {
        if (in_array($message->getLevel(), $this->config['log']) && !$this->inserting) {
            if (isset($this->config[self::FN_CONFIG_KEY])) {
                $insert = call_user_func($this->config[self::FN_CONFIG_KEY], $message);

                if (!($insert instanceof Insert)) {
                    throw new Exception('DB log adapter config ' . self::FN_CONFIG_KEY . ' function must return an array; ' . gettype($insert) . ' given');
                }
            } else {
                $insert = new Insert($this->config['table'], [
                  'time' => $message->getTime()->format('Y-m-d H:i:s'),
                  'level' => $message->getLevel(),
                  'who' => $message->getWho() ?? Log::getWho(),
                  'message' => $message->getMessage()
                ], $this->config['adapter']);
            }

            if ($insert !== null) {
                $this->inserting = true;
                $insert->exec();
                // @todo write tests for failures
                $this->inserting = false;
            }
        }
    }

}
