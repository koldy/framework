<?php declare(strict_types=1);

namespace Koldy\Log\Adapter;

use Koldy\Config\Exception as ConfigException;
use Koldy\Log\Message;

/**
 * If you plan to create your own log adapter, then please extend this class and
 * then do whatever you want to.
 *
 * @link http://koldy.net/docs/log
 */
abstract class AbstractLogAdapter
{

    /**
     * The config array got from 'options' part in config/application.php
     *
     * @var array
     */
    protected $config = null;

    /**
     * @var array
     */
    private $enabledLevels = [];

    /**
     * Constructor
     *
     * @param array $config
     *
     * @throws ConfigException
     */
    public function __construct(array $config)
    {
        $this->config = $config;

        if (!isset($config['log'])) {
            $class = get_class($this);
            throw new ConfigException("Log adapter {$class} must get 'log' key in configuration array. Check your application configuration");
        }

        foreach ($config['log'] as $level) {
            $this->enabledLevels[$level] = true;
        }
    }

    /**
     * Is given log level enabled in this adapter?
     *
     * @param string $level
     *
     * @return bool
     */
    public function isLevelEnabled(string $level)
    {
        return isset($this->enabledLevels[$level]);
    }

    /**
     * Handle message logging
     *
     * @param Message $message
     */
    abstract public function logMessage(Message $message): void;

    /**
     * Write EMERGENCY message to log
     *
     * @param Message $message
     *
     * @link http://koldy.net/docs/log#usage
     */
    public function emergency(Message $message): void
    {
        $this->logMessage($message);
    }

    /**
     * Write ALERT message to log
     *
     * @param Message $message
     *
     * @link http://koldy.net/docs/log#usage
     */
    public function alert(Message $message): void
    {
        $this->logMessage($message);
    }

    /**
     * Write CRITICAL message to log
     *
     * @param Message $message
     *
     * @link http://koldy.net/docs/log#usage
     */
    public function critical(Message $message): void
    {
        $this->logMessage($message);
    }

    /**
     * Write DEBUG message to log
     *
     * @param Message $message
     *
     * @link http://koldy.net/docs/log#usage
     */
    public function debug(Message $message): void
    {
        $this->logMessage($message);
    }

    /**
     * Write NOTICE message to log
     *
     * @param Message $message
     *
     * @link http://koldy.net/docs/log#usage
     */
    public function notice(Message $message): void
    {
        $this->logMessage($message);
    }

    /**
     * Write SQL message to log
     *
     * @param Message $message
     *
     * @link http://koldy.net/docs/log#usage
     */
    public function sql(Message $message): void
    {
        $this->logMessage($message);
    }

    /**
     * Write INFO message to log
     *
     * @param Message $message
     *
     * @link http://koldy.net/docs/log#usage
     */
    public function info(Message $message): void
    {
        $this->logMessage($message);
    }

    /**
     * Write WARNING message to log
     *
     * @param Message $message
     *
     * @link http://koldy.net/docs/log#usage
     */
    public function warning(Message $message): void
    {
        $this->logMessage($message);
    }

    /**
     * Write ERROR message to log
     *
     * @param Message $message
     *
     * @link http://koldy.net/docs/log#usage
     */
    public function error(Message $message): void
    {
        $this->logMessage($message);
    }

}
