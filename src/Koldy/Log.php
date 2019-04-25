<?php declare(strict_types=1);

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
	 * Random number generated only once per script (either HTTP or CLI), so if you reset the "who", random number
	 * will stay the same. This is used in logging so you can distinguish the same log message in two different
	 * HTTP requests or the same log message in two same CLI scripts running in parallel.
	 *
	 * @var null|int
	 */
    private static $randomNumber = null;

    /**
     * The array of enabled classes, stored as class name string
     *
     * @var array of className => true
     */
    private static $enabledAdapters = [];

    /**
     * Flag if writing to log is temporary disabled or not. Set this to true for internal use, in cases like:
     * "do not log SQL queries when DB session handler is enabled and such"
     *
     * @var bool|array
     */
    private static $temporaryDisabled = false;

    protected function __construct()
    {
    }

    protected function __clone()
    {
    }

	/**
	 * Initialize, load config and etc.
	 * @throws Exception
	 */
    public static function init(): void
    {
        if (static::$adapters === null) {
        	static::$randomNumber = rand(100000, 999999);

        	// set the "who"
            static::resetWho();

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
	 * Reset the "who" value
	 *
	 * @throws Exception
	 */
    public static function resetWho(): void
    {
	    if (Application::isCli()) {
		    static::$who = Application::getCliName() . '-' . static::$randomNumber;
	    } else {
		    static::$who = Request::ip() . '-' . static::$randomNumber;
	    }
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
	 * Get the "unique" random number generated on log class initialization. This number can be used for some other
	 * logging identifications.
	 *
	 * @return int
	 */
    public static function getRandomNumber(): int
    {
    	return static::$randomNumber;
    }

    /**
     * Temporary disable all logging
     *
     * @param array|null ...$levels
     */
    public static function temporaryDisable($levels = null): void
    {
        $disable = true;

        if (is_array($levels)) {
            $disable = $levels;
        } else if (is_string($levels)) {
            $disable = [$levels];
        }

        static::$temporaryDisabled = $disable;
    }

    /**
     * @param null|string $whichLevel
     *
     * @return bool
     */
    public static function isTemporaryDisabled(?string $whichLevel = null): bool
    {
        if ($whichLevel === null) {
            return static::$temporaryDisabled !== false;
        } else {
            if (is_array(static::$temporaryDisabled)) {
                return in_array($whichLevel, static::$temporaryDisabled);
            } else {
                return false;
            }
        }
    }

    public static function restoreTemporaryDisablement(): void
    {
        static::$temporaryDisabled = false;
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

        if (!static::isTemporaryDisabled('emergency')) {
            foreach (static::$adapters as $adapter) {
                /* @var $adapter \Koldy\Log\Adapter\AbstractLogAdapter */
                if ($adapter->isLevelEnabled(self::EMERGENCY)) {
                    $adapter->emergency((new Message(self::EMERGENCY))->setMessages($messages));
                }
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

        if (!static::isTemporaryDisabled('alert')) {
            foreach (static::$adapters as $adapter) {
                /* @var $adapter \Koldy\Log\Adapter\AbstractLogAdapter */
                if ($adapter->isLevelEnabled(self::ALERT)) {
                    $adapter->alert((new Message(self::ALERT))->setMessages($messages));
                }
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

        if (!static::isTemporaryDisabled('critical')) {
            foreach (static::$adapters as $adapter) {
                /* @var $adapter \Koldy\Log\Adapter\AbstractLogAdapter */
                if ($adapter->isLevelEnabled(self::CRITICAL)) {
                    $adapter->critical((new Message(self::CRITICAL))->setMessages($messages));
                }
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

        if (!static::isTemporaryDisabled('debug')) {
            foreach (static::$adapters as $adapter) {
                /* @var $adapter \Koldy\Log\Adapter\AbstractLogAdapter */
                if ($adapter->isLevelEnabled(self::DEBUG)) {
                    $adapter->debug((new Message(self::DEBUG))->setMessages($messages));
                }
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

        if (!static::isTemporaryDisabled('notice')) {
            foreach (static::$adapters as $adapter) {
                /* @var $adapter \Koldy\Log\Adapter\AbstractLogAdapter */
                if ($adapter->isLevelEnabled(self::NOTICE)) {
                    $adapter->notice((new Message(self::NOTICE))->setMessages($messages));
                }
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

        if (!static::isTemporaryDisabled('info')) {
            foreach (static::$adapters as $adapter) {
                /* @var $adapter \Koldy\Log\Adapter\AbstractLogAdapter */
                if ($adapter->isLevelEnabled(self::INFO)) {
                    $adapter->info((new Message(self::INFO))->setMessages($messages));
                }
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

        if (!static::isTemporaryDisabled('warning')) {
            foreach (static::$adapters as $adapter) {
                /* @var $adapter \Koldy\Log\Adapter\AbstractLogAdapter */
                if ($adapter->isLevelEnabled(self::WARNING)) {
                    $adapter->warning((new Message(self::WARNING))->setMessages($messages));
                }
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

        if (!static::isTemporaryDisabled('error')) {
            foreach (static::$adapters as $adapter) {
                /* @var $adapter \Koldy\Log\Adapter\AbstractLogAdapter */
                if ($adapter->isLevelEnabled(self::ERROR)) {
                    $adapter->error((new Message(self::ERROR))->setMessages($messages));
                }
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

        if (!static::isTemporaryDisabled('sql')) {
            foreach (static::$adapters as $adapter) {
                /* @var $adapter \Koldy\Log\Adapter\AbstractLogAdapter */
                if ($adapter->isLevelEnabled(self::SQL)) {
                    $adapter->sql((new Message(self::SQL))->setMessages($messages));
                }
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
