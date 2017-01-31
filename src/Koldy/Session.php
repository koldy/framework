<?php declare(strict_types = 1);

namespace Koldy;

use Koldy\Session\Exception as SessionException;

/**
 * The session class. Its easy to use, just make sure that your configuration
 * is valid. Everything else will be just straight forward.
 *
 */
class Session
{

    /**
     * Flag if session has been initialized or not
     *
     * @var bool
     */
    private static $initialized = false;

    /**
     * Flag if session has been write closed
     *
     * @var bool
     */
    private static $closed = false;

    /**
     * Current session ID; either value you passed to Session::start(), or ID generated by PHP
     *
     * @var string
     */
    private static $sessionId = null;

    /**
     * Initialize the session handler and session itself
     *
     * @param string|null $sessionId
     *
     * @throws Exception
     */
    private static function init(string $sessionId = null): void
    {
        if (!static::$initialized) {
            $config = Application::getConfig('session');

            session_set_cookie_params($config['cookie_life'], $config['cookie_path'], $config['cookie_domain'], $config['cookie_secure']);

            session_name($config->get('session_name', 'koldy'));

            if (($driverClass = $config->get('driver')) !== null) {
                if (($module = $config->get('module')) !== null) {
                    Application::registerModule($module);
                }

                $handler = new $driverClass($config->get('options', []));

                if (!($handler instanceof \SessionHandlerInterface)) {
                    throw new SessionException("Your session driver={$driverClass} doesn't implement \\SessionHandlerInterface, which is a must");
                }

                session_set_save_handler($handler);
            }

            if ($sessionId != null) {
                session_id($sessionId);
                static::$sessionId = $sessionId;
            }

            /*============================*/
            /******************************/
            /****/                    /****/
            /****/  session_start();  /****/
            /****/                    /****/
            /******************************/
            /*============================*/

            if ($sessionId == null) {
                static::$sessionId = session_id();
            }

            static::$initialized = true;
        }
    }

    /**
     * Get the value from session under given key name
     *
     * @param string $key
     *
     * @return mixed|null
     * @link http://koldy.net/docs/session#get
     */
    public static function get(string $key)
    {
        static::init();
        return $_SESSION[$key] ?? null;
    }

    /**
     * Set the key to the session. If key already exists, it will be overwritten
     *
     * @param string $key
     * @param mixed $value
     *
     * @throws SessionException
     * @link http://koldy.net/docs/session#set
     */
    public static function set(string $key, $value): void
    {
        if (static::$closed) {
            throw new SessionException('Can not set any other value to session because all data has been already committed');
        }

        static::init();
        $_SESSION[$key] = $value;
    }

    /**
     * Add the key to session but only if that key doesn't already exist
     *
     * @param string $key
     * @param mixed $value
     *
     * @throws SessionException
     * @link http://koldy.net/docs/session#add
     */
    public static function add(string $key, $value): void
    {
        if (static::$closed) {
            throw new SessionException('Can not set any other value to session because all data has been already committed');
        }

        static::init();

        if (!array_key_exists($key, $_SESSION)) {
            $_SESSION[$key] = $value;
        }
    }

    /**
     * Does given key exists in session or not?
     *
     * @param string $key
     *
     * @return bool
     * @link http://koldy.net/docs/session#has
     */
    public static function has(string $key): bool
    {
        static::init();
        return array_key_exists($key, $_SESSION);
    }

    /**
     * Delete/remove the key from the session data
     *
     * @param string $key
     *
     * @link http://koldy.net/docs/session#delete
     */
    public static function delete(string $key): void
    {
        static::init();

        if (array_key_exists($key, $_SESSION)) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * Get or set the key into session. If key already exists in session, then
     * its value will be returned, otherwise, function will be called, key
     * will be set with the value returned from function and that value will be
     * returned to you
     *
     * @param string $key
     * @param \Closure $functionOnSet
     *
     * @throws SessionException
     * @return mixed
     * @link http://koldy.net/docs/session#getOrSet
     */
    public static function getOrSet(string $key, \Closure $functionOnSet)
    {
        static::init();

        if (!array_key_exists($key, $_SESSION)) {
            if (static::$closed) {
                throw new SessionException('Can not set any other value to session because all data has been already committed');
            }

            $_SESSION[$key] = call_user_func($functionOnSet);
        }

        return $_SESSION[$key];
    }

    /**
     * Call session_write_close(). Usually, that function is called internally by
     * PHP on request execution end, but you can also call it by yourself. But
     * be CAREFUL! Once you call this method, you can not add or set any new
     * data to session!
     *
     * @link http://koldy.net/docs/session#close
     */
    public static function close(): void
    {
        static::init();
        session_write_close();
        static::$closed = true;
    }

    /**
     * Is session write closed or not?
     *
     * @return bool
     * @link http://koldy.net/docs/session#close
     */
    public static function isClosed(): bool
    {
        return static::$closed;
    }

    /**
     * You can start session with this method if you need that. Session start
     * will be automatically called with any of other static methods (excluding
     * hasStarted() method)
     *
     * @link http://koldy.net/docs/session#start
     *
     * @param string $sessionId
     */
    public static function start(string $sessionId = null): void
    {
        static::init($sessionId);
    }

    /**
     * Is session already started or not?
     *
     * @return bool
     * @link http://koldy.net/docs/session#start
     */
    public static function hasStarted(): bool
    {
        return static::$initialized;
    }

    /**
     * Destroy session completely
     *
     * @link http://koldy.net/docs/session#destroy
     */
    public static function destroy(): void
    {
        static::init();
        session_unset();
        session_destroy();
    }

}
