<?php declare(strict_types=1);

namespace Koldy;

/**
 * Class Cookie - helper class for working with cookies
 * @package Koldy
 */
class Cookie
{

    /**
     * Get the value from encrypted cookie value
     *
     * @param string $key
     *
     * @return string or null if cookie value doesn't exist
     * @throws Config\Exception
     * @throws Crypt\Exception
     * @throws Crypt\MalformedException
     * @throws Exception
     */
    public static function get(string $key): ?string
    {
        if (isset($_COOKIE) && array_key_exists($key, $_COOKIE)) {
            return Crypt::decrypt($_COOKIE[$key]);
        }

        return null;
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
            return $_COOKIE[$key];
        }

        return null;
    }

    /**
     * Set the cookie to encrypted value
     *
     * @param string $name the cookie name
     * @param string|number $value the cookie value
     * @param int|null $expire [optional] when will cookie expire?
     * @param string|null $path [optional] path of the cookie
     * @param string|null $domain [optional]
     * @param boolean|null $secure [optional]
     * @param boolean|null $httpOnly [optional]
     * @param string|null $samesite [optional]
     *
     * @return string
     * @throws Config\Exception
     * @throws Crypt\Exception
     * @throws Exception
     * @link http://koldy.net/docs/cookies#set
     * @example Cookie::set('last_visited', date('r'));
     */
    public static function set(string $name, string $value, ?int $expire = null, ?string $path = null, ?string $domain = null, ?bool $secure = null, ?bool $httpOnly = null, ?string $samesite = null): string
    {
        $encryptedValue = Crypt::encrypt($value);

        $options = [
	        'expires' => $expire ?? 0,
	        'path' => $path ?? '/',
	        'domain' => $domain ?? '',
	        'secure' => $secure ?? false,
	        'httponly' => $httpOnly ?? false
        ];

        if ($samesite !== null) {
        	$options['samesite'] = $samesite;
        }

        setcookie($name, $encryptedValue, $options);
        return $encryptedValue;
    }

    /**
     * Set the raw cookie value, without encryption
     *
     * @param string $name the cookie name
     * @param string|number $value the cookie value
     * @param int|null $expire [optional] when will cookie expire?
     * @param string|null $path [optional] path of the cookie
     * @param string|null $domain [optional]
     * @param boolean|null $secure [optional]
     * @param boolean|null $httpOnly [optional]
     * @param string|null $samesite [optional]
     *
     * @link http://koldy.net/docs/cookies#set
     * @example Cookie::set('last_visited', date('r'));
     * @return string
     */
    public static function rawSet(string $name, string $value, ?int $expire = null, ?string $path = null, ?string $domain = null, ?bool $secure = null, ?bool $httpOnly = null, ?string $samesite = null): string
    {
	    $options = [
		    'expires' => $expire ?? 0,
		    'path' => $path ?? '/',
		    'domain' => $domain ?? '',
		    'secure' => $secure ?? false,
		    'httponly' => $httpOnly ?? false
	    ];

	    if ($samesite !== null) {
		    $options['samesite'] = $samesite;
	    }

        setcookie($name, $value, $options);
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
     * @param null|string $path
     * @param null|string $domain
     * @param bool|null $secure
     * @param bool|null $httpOnly
     * @param string|null $samesite
     *
     * @link http://koldy.net/docs/cookies#delete
     */
    public static function delete(string $name, ?string $path = null, ?string $domain = null, ?bool $secure = null, ?bool $httpOnly = null, ?string $samesite = null): void
    {
	    $options = [
		    'expires' => $expire ?? 0,
		    'path' => $path ?? '/',
		    'domain' => $domain ?? '',
		    'secure' => $secure ?? false,
		    'httponly' => $httpOnly ?? false
	    ];

	    if ($samesite !== null) {
		    $options['samesite'] = $samesite;
	    }

        setcookie($name, '', time() - 3600 * 24, $options);
    }

}
