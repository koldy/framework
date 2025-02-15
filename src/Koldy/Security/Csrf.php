<?php declare(strict_types=1);

namespace Koldy\Security;

use Koldy\Application;
use Koldy\Cookie;
use Koldy\Config\Exception as ConfigException;
use Koldy\Security\Csrf\Token;
use Koldy\Session;
use Koldy\Security\Exception as SecurityException;

class Csrf
{

    /**
     * @deprecated
     */
	protected const ENABLED = 'enabled';

	protected const PARAMETER_NAME = 'parameter_name';
	protected const COOKIE_NAME = 'cookie_name';
	protected const SESSION_KEY_NAME = 'session_key_name';

    protected static Token|null $token = null;

    /**
     * Initialized CSRF config will be stored here
     */
    protected static array|null $config = null;

    /**
     * Initialize CSRF config
     *
     * @param array|null $config
     * @param bool $reInit
     *
     * @throws ConfigException
     * @throws \Koldy\Exception
     */
    public static function init(array|null $config = null, bool $reInit = false): void
    {
        if (static::$config === null || $reInit) {
            if ($config === null) {
                $config = Application::getConfig('application');
                static::$config = $config->getArrayItem('security', 'csrf') ?? [
	                self::PARAMETER_NAME => null,
	                self::COOKIE_NAME => 'csrf_token',
	                self::SESSION_KEY_NAME => 'csrf_token'
                ];
            } else {
                static::$config = $config;
            }

            /* // CSRF is not started by default by framework any more
            if (!array_key_exists(self::ENABLED, static::$config)) {
                throw new ConfigException('Missing key \'enabled\' in security/CSRF config');
            }
            */

            //if (static::$config['enabled']) {
                // check CSRF config

                foreach ([self::PARAMETER_NAME, self::COOKIE_NAME, self::SESSION_KEY_NAME] as $key) {
                    if (!array_key_exists($key, static::$config)) {
                        throw new ConfigException("Missing {$key} key in security/CSRF config");
                    }
                }
            //}
        }
    }

    /**
     * Get the session key under which token is stored in session
     *
     * @return string
     * @throws ConfigException
     * @throws \Koldy\Exception
     */
    public static function getSessionKeyName(): ?string
    {
        static::init();
        return static::$config[self::SESSION_KEY_NAME];
    }

    /**
     * Get the cookie key under which token is stored on client's computer
     *
     * @return string|null
     * @throws ConfigException
     * @throws \Koldy\Exception
     */
    public static function getCookieName(): ?string
    {
        static::init();
        return static::$config[self::COOKIE_NAME] ?? null;
    }

    /**
     * Set the CSRF token into current session.
     *
     * @param null|string $token Your token, leave null if you want framework to generate it
     * @param null|int $length
     *
     * @return Token
     * @throws ConfigException
     * @throws Session\Exception
     * @throws \Koldy\Exception
     */
    public static function generate(string|null $token = null, int|null $length = null): Token
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

        $config = Application::getConfig('session');

        $cookieName = static::getCookieName();
        if (is_string($cookieName) && strlen($cookieName) > 0) {
            $cookie = Cookie::rawSet(
            	$cookieName,
	            $token,
	            0,
	            '/',
	            $config->get('domain') ?? '',
	            $config->get('cookie_secure') ?? false,
	            false,
	            $config->get('cookie_samesite')
            );
        } else {
            $cookie = null;
        }

        $token = new Token($token, $cookie);
        static::$token = $token;

        $sessionKeyName = static::getSessionKeyName();
        if (is_string($sessionKeyName) && strlen($sessionKeyName) > 0) {
            Session::set($sessionKeyName, $token);
        }

        return $token;
    }

    /**
     * Is CSRF check enabled in config or not
     *
     * @return bool
     * @throws ConfigException
     * @throws \Koldy\Exception
     */
    public static function isEnabled(): bool
    {
        static::init();
        return static::getParameterName() !== null;
    }

    /**
     * Is there CSRF token stored in the session?
     *
     * @return bool
     * @throws ConfigException
     * @throws \Koldy\Exception
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
     * @throws ConfigException
     * @throws Exception
     * @throws \Koldy\Exception
     */
    public static function getStoredToken(): Token
    {
        if (static::$token !== null) {
            return static::$token;
        }

        $sessionKeyName = static::getSessionKeyName();

        if ($sessionKeyName === null || strlen($sessionKeyName) == 0) {
            throw new Exception('Can not get stored CSRF token when session key is not set');
        }

        $token = Session::get($sessionKeyName);

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
     * @throws ConfigException
     * @throws Exception
     * @throws \Koldy\Exception
     */
    public static function hasCookieToken(): bool
    {
        $cookieName = static::getCookieName();

        if ($cookieName === null || strlen($cookieName) == 0) {
            throw new Exception('Can not check if CSRF token is in cookie when cookie name is not set in CSRF configuration');
        }

        return Cookie::has($cookieName);
    }

    /**
     * Check if given CSRF token is valid
     *
     * @param string $token
     *
     * @return bool
     * @throws ConfigException
     * @throws Exception
     * @throws \Koldy\Exception
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
     * @return string|null
     * @throws ConfigException
     * @throws \Koldy\Exception
     */
    public static function getParameterName(): ?string
    {
        static::init();
        return static::$config[self::PARAMETER_NAME] ?? null;
    }

    /**
     * Get prepared HTML input that can be used directly in forms
     *
     * @return string
     * @throws ConfigException
     * @throws Exception
     * @throws \Koldy\Exception
     *
     * @deprecated Create your own CSRF token distribution system instead of relying on this method
     */
    public static function getHtmlInputHidden(): string
    {
        static::init();

        if (($parameterName = static::getParameterName()) !== null) {
            $csrfValue = static::getStoredToken()->getToken();
            return sprintf('<input type="hidden" name="%s" value="%s"/>', $parameterName, $csrfValue);
        } else {
            return '';
        }
    }

    /**
     * Get prepared prepared meta tags from where you can read your CSRF values
     *
     * @return string
     * @throws ConfigException
     * @throws Exception
     * @throws \Koldy\Exception
     *
     * @deprecated Create your own CSRF token distribution system instead of relying on this method
     */
    public static function getMetaTags(): string
    {
        static::init();
        if (($parameterName = static::getParameterName()) !== null) {
            $csrfValue = static::getStoredToken()->getToken();
            return sprintf('<meta name="csrf_name" content="%s"/><meta name="csrf_value" content="%s"/>', $parameterName, $csrfValue);
        } else {
            return '';
        }
    }

}
