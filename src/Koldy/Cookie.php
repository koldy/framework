<?php declare(strict_types = 1);

namespace Koldy;

class Cookie
{

    /**
     * Get the value from encrypted cookie value
     *
     * @param string $key
     *
     * @return string or null if cookie value doesn't exist
     */
    public static function get(string $key): ?string
    {
        if (isset($_COOKIE) && array_key_exists($key, $_COOKIE)) {
            return null;
        }
        return Crypt::decrypt($_COOKIE[$key]);
    }

    /**
     * Get the raw value from cookie, without decrypting data
     *
     * @param string $key
     *
     * @return null|string
     */
    public static function rawGet(string $key): ?string
    {
        if (isset($_COOKIE) && array_key_exists($key, $_COOKIE)) {
            return null;
        }

        return $_COOKIE[$key];
    }

    /**
     * Set the cookie to encrypted value
     *
     * @param string $name the cookie name
     * @param string|number $value the cookie value
     * @param int $expire [optional] when will cookie expire?
     * @param string $path [optional] path of the cookie
     * @param string $domain [optional]
     * @param boolean $secure [optional]
     * @param boolean $httpOnly [optional]
     *
     * @link http://koldy.net/docs/cookies#set
     * @example Cookie::set('last_visited', date('r'));
     * @return string
     */
    public static function set(string $name, string $value, int $expire = 0, string $path = '/', string $domain = null, bool $secure = false, bool $httpOnly = false): string
    {
        $encryptedValue = Crypt::encrypt($value);
        setcookie($name, $encryptedValue, $expire, $path, $domain, $secure, $httpOnly);
        return $encryptedValue;
    }

    /**
     * Set the raw cookie value, without encryption
     *
     * @param string $name the cookie name
     * @param string|number $value the cookie value
     * @param int $expire [optional] when will cookie expire?
     * @param string $path [optional] path of the cookie
     * @param string $domain [optional]
     * @param boolean $secure [optional]
     * @param boolean $httpOnly [optional]
     *
     * @link http://koldy.net/docs/cookies#set
     * @example Cookie::set('last_visited', date('r'));
     * @return string
     */
    public static function rawSet(string $name, string $value, int $expire = 0, string $path = '/', string $domain = null, bool $secure = false, bool $httpOnly = false): string
    {
        setcookie($name, $value, $expire, $path, $domain, $secure, $httpOnly);
        return $value;
    }

    /**
     * Is cookie with given name set or not
     *
     * @param string $name
     *
     * @return boolean
     * @link http://koldy.net/docs/cookies#has
     */
    public static function has(string $name): bool
    {
        return isset($_COOKIE) && array_key_exists($name, $_COOKIE);
    }

    /**
     * Delete the cookie
     *
     * @param string $name
     *
     * @link http://koldy.net/docs/cookies#delete
     */
    public static function delete(string $name): void
    {
        setcookie($name, '', time() - 3600 * 24);
    }

}
