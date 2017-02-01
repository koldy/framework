<?php declare(strict_types = 1);

namespace Koldy;

use Koldy\Config\Exception as ConfigException;
use Koldy\Log\Adapter\AbstractLogAdapter;
use Koldy\Log\Message;

/**
 * Class to handle the log and writing to log. Be aware that using too much of log slows down the
 * complete application, while other processes are waiting to finish your log. Of course, you can rapidly optimize
 * this by using slightly different syntax.
 *
 * You are encouraged to use log in development, but reduce logs in production mode as much as you can. Always
 * log only important data and never log the code that will always execute successfully. Don't expose sensitive data!
 *
 * If you have enabled email logging, then this script will send you log message(s) to your error mail. To reduce
 * SPAM, if there are a lot of error messages to send, all other log messages will be mailed at once as well. Lets
 * say you have 5 info log messages, 1 notice and 1 error - you'll receive error mail with all messages logged
 * with Log class even if those message won't be written to your Log adapter.
 *
 * @link http://koldy.net/docs/log
 *
 */
class Log
{

    private const EMERGENCY = 'emergency';
    private const ALERT = 'alert';
    private const CRITICAL = 'critical';
    private const DEBUG = 'debug';
    private const NOTICE = 'notice';
    private const INFO = 'info';
    private const WARNING = 'warning';
    private const ERROR = 'error';
    private const SQL = 'sql';

    /**
     * The array of only enabled writer instances for this request
     *
     * @var array
     */
    private static $adapters = null;

    /**
     * The array of enabled log levels, combined for all loggers
     *
     * @var array of level => true, possible levels are: emergency, alert, critical, error, warning, notice, info, debug and sql
     */
    private static $enabledLevels = [];

    /**
     * @var string
     */
    private static $who = null;

    /**
     * The array of enabled classes, stored as class name string
     *
     * @var array of className => true
     */
    private static $enabledAdapters = [];

    protected function __construct()
    {
    }

    protected function __clone()
    {
    }

    /**
     * Initialize, load config and etc.
     */
    public static function init(): void
    {
        if (static::$adapters === null) {
            if (Application::isCli()) {
                static::$who = Application::getCliName() . '-' . time();
            } else {
                static::$who = Request::ip() . '-' . rand(100000, 999999);
            }

            static::$adapters = [];
            $configs = Application::getConfig('application')->get('log', []);

            $count = 0;
            foreach ($configs as $index => $config) {
                $enabled = $config['enabled'] && is_array($config['options']['log']) && count($config['options']['log']) > 0;

                // TODO: Register module if needed

                if ($enabled) {
                    if (!isset($config['adapter_class'])) {
                        throw new ConfigException("Logger[{$index}] defined in application config is missing adapter_class key");
                    }

                    // if the config is enabled, then make new instance
                    $adapter = $config['adapter_class'];

                    static::$adapters[$count] = new $adapter($config['options']);

                    // Log class must be instance of AbstractLogAdapter
                    if (!(static::$adapters[$count] instanceof AbstractLogAdapter)) {
                        throw new Exception("Log adapter {$adapter} must extend AbstractLogAdapter");
                    }

                    static::$enabledAdapters[$config['adapter_class']] = true;

                    foreach ($config['options']['log'] as $level) {
                        static::$enabledLevels[$level] = true;
                    }

                    $count++;
                }
            }
        }
    }

    /**
     * Is there any log adapter enabled in this moment?
     *
     * @return boolean
     */
    public static function isEnabled(): bool
    {
        static::init();
        return count(static::$adapters) > 0;
    }

    /**
     * @param string $level
     *
     * @return bool
     */
    public static function isEnabledLevel(string $level): bool
    {
        return isset(static::$enabledLevels[$level]);
    }

    /**
     * Was logger under given class name enabled or not?
     *
     * @param string $className
     *
     * @return bool
     */
    public static function isEnabledLogger(string $className): bool
    {
        return isset(static::$enabledAdapters[$className]);
    }

    /**
     * Set the "who" - it'll be visible in logs as "who did that".
     *
     * If you pass string, you'll set the "who"
     *
     * @param string $who
     */
    public static function setWho(string $who): void
    {
        static::$who = $who;
    }

    /**
     * Get the "who"
     *
     * @return string
     */
    public static function getWho(): string
    {
        return static::$who;
    }

    /**
     * Write EMERGENCY message to log
     *
     * @param array|string ...$messages
     *
     * @link http://koldy.net/docs/log#usage
     */
    public static function emergency(...$messages): void
    {
        static::init();

        foreach (static::$adapters as $adapter) {
            /* @var $adapter \Koldy\Log\Adapter\AbstractLogAdapter */
            if ($adapter->isLevelEnabled(self::EMERGENCY)) {
                $adapter->emergency((new Message(self::EMERGENCY))->setMessages($messages));
            }
        }
    }

    /**
     * Write ALERT message to log
     *
     * @param array|string ...$messages
     *
     * @link http://koldy.net/docs/log#usage
     */
    public static function alert(...$messages): void
    {
        static::init();

        foreach (static::$adapters as $adapter) {
            /* @var $adapter \Koldy\Log\Adapter\AbstractLogAdapter */
            if ($adapter->isLevelEnabled(self::ALERT)) {
                $adapter->alert((new Message(self::ALERT))->setMessages($messages));
            }
        }
    }

    /**
     * Write CRITICAL message to log
     *
     * @param array|string ...$messages
     *
     * @link http://koldy.net/docs/log#usage
     */
    public static function critical(...$messages): void
    {
        static::init();

        foreach (static::$adapters as $adapter) {
            /* @var $adapter \Koldy\Log\Adapter\AbstractLogAdapter */
            if ($adapter->isLevelEnabled(self::CRITICAL)) {
                $adapter->critical((new Message(self::CRITICAL))->setMessages($messages));
            }
        }
    }

    /**
     * Write DEBUG message to log
     *
     * @param array|string ...$messages
     *
     * @link http://koldy.net/docs/log#usage
     */
    public static function debug(...$messages): void
    {
        static::init();

        foreach (static::$adapters as $adapter) {
            /* @var $adapter \Koldy\Log\Adapter\AbstractLogAdapter */
            if ($adapter->isLevelEnabled(self::DEBUG)) {
                $adapter->debug((new Message(self::DEBUG))->setMessages($messages));
            }
        }
    }

    /**
     * Write NOTICE message to log
     *
     * @param array|string ...$messages
     *
     * @link http://koldy.net/docs/log#usage
     */
    public static function notice(...$messages): void
    {
        static::init();

        foreach (static::$adapters as $adapter) {
            /* @var $adapter \Koldy\Log\Adapter\AbstractLogAdapter */
            if ($adapter->isLevelEnabled(self::NOTICE)) {
                $adapter->notice((new Message(self::NOTICE))->setMessages($messages));
            }
        }
    }

    /**
     * Write INFO message to log
     *
     * @param array|string ...$messages
     *
     * @link http://koldy.net/docs/log#usage
     */
    public static function info(...$messages): void
    {
        static::init();

        foreach (static::$adapters as $adapter) {
            /* @var $adapter \Koldy\Log\Adapter\AbstractLogAdapter */
            if ($adapter->isLevelEnabled(self::INFO)) {
                $adapter->info((new Message(self::INFO))->setMessages($messages));
            }
        }
    }

    /**
     * Write WARNING message to log
     *
     * @param array|string ...$messages
     *
     * @link http://koldy.net/docs/log#usage
     */
    public static function warning(...$messages): void
    {
        static::init();

        foreach (static::$adapters as $adapter) {
            /* @var $adapter \Koldy\Log\Adapter\AbstractLogAdapter */
            if ($adapter->isLevelEnabled(self::WARNING)) {
                $adapter->warning((new Message(self::WARNING))->setMessages($messages));
            }
        }
    }

    /**
     * Write ERROR message to log
     *
     * @param array|string ...$messages
     *
     * @link http://koldy.net/docs/log#usage
     */
    public static function error(...$messages): void
    {
        static::init();

        foreach (static::$adapters as $adapter) {
            /* @var $adapter \Koldy\Log\Adapter\AbstractLogAdapter */
            if ($adapter->isLevelEnabled(self::ERROR)) {
                $adapter->error((new Message(self::ERROR))->setMessages($messages));
            }
        }
    }

    /**
     * Write SQL query to log
     *
     * @param array|string ...$messages
     *
     * @link http://koldy.net/docs/log#usage
     */
    public static function sql(...$messages): void
    {
        static::init();

        foreach (static::$adapters as $adapter) {
            /* @var $adapter \Koldy\Log\Adapter\AbstractLogAdapter */
            if ($adapter->isLevelEnabled(self::SQL)) {
                $adapter->sql((new Message(self::SQL))->setMessages($messages));
            }
        }
    }

    /**
     * Log message with prepared Log\Message instance
     *
     * @param Message $message
     */
    public static function message(Message $message): void
    {
        static::init();

        foreach (static::$adapters as $adapter) {
            /* @var $adapter \Koldy\Log\Adapter\AbstractLogAdapter */
            if ($adapter->isLevelEnabled($message->getLevel())) {
                $adapter->logMessage($message);
            }
        }
    }

}
