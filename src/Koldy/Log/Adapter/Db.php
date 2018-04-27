<?php declare(strict_types=1);

namespace Koldy\Log\Adapter;

use Koldy\Config\Exception as ConfigException;
use Koldy\Db as DbAdapter;
use Koldy\Db\Adapter\AbstractAdapter;
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
     * @var string|null
     */
    protected $adapter;

    /**
     * @var string
     */
    protected $table;

    /**
     * The flag if query is being inserted into database to prevent recursion
     *
     * @var boolean
     */
    protected $inserting = false;

    /**
     * @var callable|null
     */
    protected $insertFn = null;

    /**
     * @var bool
     */
    protected $disableLog = true;

    /**
     * Construct the DB writer
     *
     * @param array $config
     *
     * @throws ConfigException
     */
    public function __construct(array $config)
    {
        $this->adapter = $config['adapter'] ?? null;
        $this->table = $config['table'] ?? 'log';
        $this->disableLog = array_key_exists('disable_log', $config) ? (bool)$config['disable_log'] : true;

        if (isset($config['get_insert_fn'])) {
            if (!is_callable($config['get_insert_fn'])) {
                throw new ConfigException('\'get_insert_fn\' in DB log adapter options is not instance of Callable');
            } else {
                $this->insertFn = $config['get_insert_fn'];
            }
        }

        parent::__construct($config);
    }

    /**
     * @return string
     */
    protected function getTableName(): string
    {
        return $this->table;
    }

    /**
     * @return null|string
     */
    protected function getAdapterConnection(): ?string
    {
        return $this->adapter;
    }

    /**
     * @return AbstractAdapter
     * @throws ConfigException
     * @throws \Koldy\Db\Exception
     * @throws \Koldy\Exception
     */
    protected function getAdapter(): AbstractAdapter
    {
        return DbAdapter::getAdapter($this->getAdapterConnection());
    }

    /**
     * @param Message $message
     *
     * @throws Exception
     * @throws \Exception
     */
    public function logMessage(Message $message): void
    {
        if (in_array($message->getLevel(), $this->config['log']) && !$this->inserting) {
            if ($this->insertFn !== null) {
                $insert = call_user_func($this->insertFn, $message, $this->config);

                if ($insert !== null) {

                    if (!is_object($insert)) {
                        throw new Exception('\'get_insert_fn\' function must return an instance of Insert; instead got type of ' . gettype($insert));
                    } else {
                        // it is object, but what type
                        if (!($insert instanceof Insert)) {
                            throw new Exception('\'get_insert_fn\' function must return an instance of Insert; instead got instance of ' . get_class($insert));
                        }
                    }

                }
            } else {
                $data = [
                  'time' => $message->getTime()->format('Y-m-d H:i:s'),
                  'level' => $message->getLevel(),
                  'who' => $message->getWho() ?? Log::getWho(),
                  'message' => $message->getMessage()
                ];

                $insert = new Insert($this->getTableName(), $data, $this->getAdapterConnection());
            }

            if ($insert !== null) {
                $this->inserting = true;

                try {

                    Log::temporaryDisable();
                    $insert->exec();

                } catch (\Exception $e) {
                    throw $e;

                } finally {
                    Log::restoreTemporaryDisablement();
                    // @todo write tests for failures
                    $this->inserting = false;
                }
            }
        }
    }

}
