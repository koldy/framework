<?php declare(strict_types=1);

namespace Koldy\Log\Adapter;

use Koldy\Application;
use Koldy\Config\Exception as ConfigException;
use Koldy\Convert;
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

    /**
     * Dump some common stuff into log according to config
     *
     * @throws Convert\Exception
     * @throws \Koldy\Exception
     */
    public function dump(): void
    {
        $dump = $this->config['dump'] ?? [];

        if (is_array($dump) && count($dump) > 0) {
            // 'speed', 'included_files', 'include_path', 'whitespace'
            $dump = array_flip($dump);

            $url = isset($_SERVER['REQUEST_METHOD']) ? ($_SERVER['REQUEST_METHOD'] . '=' . Application::getCurrentURL()) : ('CLI=' . Application::getCliName());

            if (array_key_exists('speed', $dump)) {
                $executedIn = Application::getRequestExecutionTime();
                $count = count(get_included_files());
                $this->logMessage(new Message('notice', "{$url} EXECUTED IN {$executedIn}ms, used {$count} files"));
            }

            if (array_key_exists('memory', $dump)) {
                $memory = memory_get_usage();
                $peak = memory_get_peak_usage();
                $allocatedMemory = memory_get_peak_usage(true);
                $memoryLimit = ini_get('memory_limit') ?? -1;

                $memoryKb = round($memory / 1024, 2);
                $peakKb = round($peak / 1024, 2);
                $allocatedMemoryKb = round($allocatedMemory / 1024, 2);

                $limit = '';
                $peakSpent = '';

                if ($memoryLimit > 0) {
                    $limitInt = Convert::stringToBytes($memoryLimit);
                    $limit = ", limit: {$memoryLimit}";

                    $spent = round($peak / $limitInt * 100, 2);
                    $peakSpent = " ({$spent}% of limit)";
                }

                $this->logMessage(new Message('notice', "{$url} CONSUMED MEM: current: {$memoryKb}kb, peak: {$peakKb}kb{$peakSpent}, allocated: {$allocatedMemoryKb}kb{$limit}"));
            }

            if (array_key_exists('included_files', $dump)) {
                $this->logMessage(new Message('notice', 'Included files: ' . print_r(get_included_files(), true)));
            }

            if (array_key_exists('whitespace', $dump)) {
                $this->logMessage(new Message('notice', "END OF {$url}\n" . str_repeat('#', 120) . "\n\n"));
            }
        }
    }

}
