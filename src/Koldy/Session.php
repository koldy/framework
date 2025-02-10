<?php declare(strict_types=1);

namespace Koldy;

use Closure;
use Koldy\Session\Exception as SessionException;

/**
 * The session class. It's easy to use, just make sure that your configuration
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
	protected static bool $initialized = false;

	/**
	 * Flag if session has been write closed
	 *
	 * @var bool
	 */
	protected static bool $closed = false;

	/**
	 * Current session ID; either value you passed to Session::start(), or ID generated by PHP
	 *
	 * @var string|null
	 */
	protected static string | null $sessionId = null;

	/**
	 * Initialize the session handler and session itself
	 *
	 * @param string|null $sessionId
	 *
	 * @throws Exception
	 */
	protected static function init(string|null $sessionId = null): void
	{
		if (!static::$initialized) {
			$config = Application::getConfig('session');

			$transportOptions = $config->get('transport') ?? [];
			$transport = Util::pick('type', $transportOptions, 'cookie', ['cookie', 'header']);
			$headerName = $transport === 'header' ? Util::pick('header_name', $transportOptions, 'X-SESSION') : null;

			if ($transport === 'cookie') {
				// when we transfer session ID via cookie, we need to set some options for session cookie
				$samesite = $config->get('cookie_samesite');

				$options = [
					'lifetime' => $config->get('cookie_life') ?? 0,
					'path' => $config->get('cookie_path') ?? '/',
					'domain' => $config->get('cookie_domain') ?? '',
					'secure' => $config->get('cookie_secure') ?? false,
					'httponly' => $config->get('http_only') ?? false
				];

				if ($samesite !== null) {
					$options['samesite'] = $samesite;
				}

				session_set_cookie_params($options);
			}

			if ($transport === 'header' && $sessionId === null) {
				// if this is header transport, we need to get the session ID from header if it's not requested through init
				$phpHeaderName = 'HTTP_' . strtoupper(str_replace('-', '_', $headerName));
				$sessionId = Util::pick($phpHeaderName, $_SERVER);
			}

			session_name($config->get('session_name') ?? 'koldy');

			if (($adapterClass = $config->get('adapter_class')) !== null) {
				if (($module = $config->get('module')) !== null) {
					Application::registerModule($module);
				}

				$handler = new $adapterClass($config->get('options') ?? []);

				if (!($handler instanceof \SessionHandlerInterface)) {
					throw new SessionException("Your session adapter={$adapterClass} doesn't implement \\SessionHandlerInterface, which is a must");
				}

				session_set_save_handler($handler);
			}

			if ($sessionId !== null) {
				session_id($sessionId);
				static::$sessionId = $sessionId;
			}

			$options = [];

			if (version_compare(PHP_VERSION, '8.4', '<')) {
				$options['sid_length'] = $config->get('sid_length') ?? 40;
				$options['sid_bits_per_character'] = $config->get('sid_bits_per_character') ?? 6;
			}

			/******************************************/
			session_start($options);
			/******************************************/

			if ($sessionId === null) {
				$sessId = session_id();

				if ($sessId === false) {
					throw new SessionException('Something went wrong, PHP session_id() function returned false instead of string');
				}

				static::$sessionId = $sessId;
			}

			if ($transport === 'header') {
				// if we're dealing with header transport, we need to know basically two things:
				// the name of the header we'll be using and the value of the session ID

				header("{$headerName}: " . static::$sessionId);
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
	 * @throws Exception
	 * @link http://koldy.net/docs/session#get
	 */
	public static function get(string $key): mixed
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
	 * @throws Exception
	 * @throws SessionException
	 * @link http://koldy.net/docs/session#set
	 */
	public static function set(string $key, mixed $value): void
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
	 * @throws Exception
	 * @throws SessionException
	 * @link http://koldy.net/docs/session#add
	 */
	public static function add(string $key, mixed $value): void
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
	 * @throws Exception
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
	 * @throws Exception
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
	 * @param Closure $functionOnSet
	 *
	 * @return mixed
	 * @throws Exception
	 * @throws SessionException
	 * @link http://koldy.net/docs/session#getOrSet
	 */
	public static function getOrSet(string $key, Closure $functionOnSet): mixed
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
	 * @throws Exception
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
	 * @param string|null $sessionId
	 * @throws Exception
	 */
	public static function start(string|null $sessionId = null): void
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
	 * Get the current session ID
	 */
	public static function id(): string|null
	{
		return static::$sessionId;
	}

	/**
	 * Destroy session completely
	 *
	 * @link http://koldy.net/docs/session#destroy
	 * @throws Exception
	 */
	public static function destroy(): void
	{
		static::init();
		session_unset();
		session_destroy();
	}

}
