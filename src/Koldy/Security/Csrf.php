<?php declare(strict_types = 1);

namespace Koldy\Security;

use Koldy\Application;
use Koldy\Cookie;
use Koldy\Config\Exception as ConfigException;
use Koldy\Security\Csrf\Token;
use Koldy\Session;
use Koldy\Security\Exception as SecurityException;

class Csrf
{

    private const ENABLED = 'enabled';
    private const PARAMETER_NAME = 'parameter_name';
    private const COOKIE_NAME = 'cookie_name';
    private const SESSION_KEY_NAME = 'session_key_name';

    /**
     * @var Token
     */
    private static $token = null;

    /**
     * Initialized CSRF config will be stored here
     *
     * @var array
     */
    private static $config = null;

    /**
     * Initialize CSRF config
     *
     * @param array $config
     * @param bool $reInit
     *
     * @throws ConfigException
     */
    public static function init(array $config = null, bool $reInit = false)
    {
        if (static::$config === null || $reInit) {
            if ($config === null) {
                $config = Application::getConfig('application');
                static::$config = $config->getArrayItem('security', 'csrf', [
                  self::ENABLED => false,
                  self::PARAMETER_NAME => 'csrf',
                  self::COOKIE_NAME => 'csrf_token',
                  self::SESSION_KEY_NAME => 'csrf_token'
                ]);
            } else {
                static::$config = $config;
            }

            if (!isset(static::$config[self::ENABLED])) {
                throw new ConfigException('Missing key \'enabled\' in security/CSRF config');
            }

            if (static::$config['enabled']) {
                // check CSRF config

                foreach ([self::PARAMETER_NAME, self::COOKIE_NAME, self::SESSION_KEY_NAME] as $key) {
                    if (!isset(static::$config[$key])) {
                        throw new ConfigException("Missing {$key} key in security/CSRF config");
                    }
                }
            }
        }
    }

    /**
     * Get the session key under which token is stored in session
     *
     * @return string
     */
    public static function getSessionKeyName(): string
    {
        static::init();
        return static::$config[self::SESSION_KEY_NAME];
    }

    /**
     * Get the cookie key under which token is stored on client's computer
     *
     * @return string
     */
    public static function getCookieName(): string
    {
        static::init();
        return static::$config[self::COOKIE_NAME];
    }

    /**
     * Set the CSRF token into current session.
     *
     * @param null|string $token Your token, leave null if you want framework to generate it
     * @param null|int $length
     *
     * @return Token
     */
    public static function generate(string $token = null, int $length = null): Token
    {
        if ($token == null) {
            if ($length == null) {
                $length = 64;
            }

            // generate token here
            if (function_exists('openssl_random_pseudo_bytes')) {
                $token = bin2hex(openssl_random_pseudo_bytes($length));

                if (strlen($token) > $length) {
                    // we have a string, now, take some random part there
                    $token = substr($token, rand(0, strlen($token) - $length), $length);
                }
            } else {
                // a fallback if openssl_random_pseudo_bytes is not accessible for some reason
                $someString = time() . '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ' . Application::getKey();
                $token = substr(str_shuffle($someString), 0, $length);
            }
        }

        $token = new Token($token, Cookie::set(static::getCookieName(), $token));
        Session::set(static::getSessionKeyName(), $token);
        static::$token = $token;

        return $token;
    }

    /**
     * Is CSRF check enabled or not
     *
     * @return bool
     */
    public static function isEnabled(): bool
    {
        static::init();
        return static::$config[self::ENABLED];
    }

    /**
     * Is there CSRF token stored in the session?
     *
     * @return bool
     */
    public static function hasTokenStored(): bool
    {
        if (static::$token === null) {
            try {
                static::$token = static::getStoredToken();
            } catch (SecurityException $e) {
                // do nothing, so false will be returned
            }
        }

        return static::$token !== null;
    }

    /**
     * Get currently stored CSRF token from session
     * @return Token
     * @throws Exception
     */
    public static function getStoredToken(): Token
    {
        if (static::$token !== null) {
            return static::$token;
        }

        $token = Session::get(static::getSessionKeyName());

        if ($token === null) {
            throw new SecurityException('There is no stored token on backend');
        }

        static::$token = $token;
        return $token;
    }

    /**
     * Is there a cookie with CSRF token present in this request?
     *
     * @return bool
     */
    public static function hasCookieToken(): bool
    {
        return Cookie::has(static::getCookieName());
    }

    /**
     * Check if given CSRF token is valid
     *
     * @param string $token
     *
     * @return bool
     */
    public static function isTokenValid(string $token): bool
    {
        if (!static::hasTokenStored()) {
            return false;
        }

        $storedToken = static::getStoredToken();
        return $storedToken->getToken() === $token;
    }

    /**
     * Get the name of parameter that has to be sent within the form
     *
     * @return string
     */
    public static function getParameterName(): string
    {
        static::init();
        return static::$config[self::PARAMETER_NAME];
    }

    /**
     * Get prepared HTML input that can be used directly in forms
     *
     * @return string
     */
    public static function getHtmlInputHidden(): string
    {
        static::init();
        $parameterName = static::getParameterName();
        $csrfValue = static::getStoredToken()->getToken();
        return sprintf('<input type="hidden" name="%s" value="%s"/>', $parameterName, $csrfValue);
    }

}
