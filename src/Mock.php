<?php declare(strict_types=1);

namespace Koldy;

use Koldy\Security\Exception as SecurityException;
use ReflectionClass;
use ReflectionException;

/**
 * Mock class for simulating HTTP requests in testing environments. You can safely use it in unit tests to simulate
 * incoming HTTP requests.
 *
 * Be aware that you can not override PHP's $_REQUEST global variable because Koldy Framework is not using it anywhere.
 * Therefore, you shouldn't use it either.
 *
 */
class Mock
{

	/**
	 * Store original superglobals to restore them later
	 */
	private static array $original = [
		'get' => [],
		'post' => [],
		'server' => [],
		'files' => [],
		'cookie' => [],
		'session' => []
	];

	/**
	 * Flag to track if mocking is active
	 */
	private static bool $isMocking = false;

	/**
	 * Mock a JSON request
	 *
	 * @param string $method HTTP method
	 * @param string $uri Request URI
	 * @param array $data JSON data
	 * @param array $headers Additional headers
	 *
	 * @return void
	 * @throws SecurityException
	 */
	public static function json(
		string $method,
		string $uri,
		array $data,
		array $headers = []
	): void {
		$jsonData = json_encode($data);
		$contentLength = strlen($jsonData);

		$defaultHeaders = [
			'Content-Type' => 'application/json',
			'Content-Length' => (string)$contentLength,
			'Accept' => 'application/json'
		];

		self::request($method, $uri, $data, array_merge($defaultHeaders, $headers), $jsonData);
	}

	/**
	 * Mock a complete HTTP request
	 *
	 * @param string $method HTTP method (GET, POST, PUT, DELETE, etc.)
	 * @param string $uri Request URI
	 * @param array $params Request parameters
	 * @param array $headers HTTP headers
	 * @param string $rawData Raw request data
	 * @param array $files Uploaded files
	 *
	 * @return void
	 * @throws SecurityException
	 */
	public static function request(
		string $method,
		string $uri,
		array $params = [],
		array $headers = [],
		string $rawData = '',
		array $files = []
	): void {
		self::ensureTestingEnvironment();

		// Start fresh
		self::reset();
		self::start();

		// Parse URI
		$parsedUrl = parse_url($uri);
		$path = $parsedUrl['path'] ?? '/';

		// Parse query string if present
		$queryParams = [];
		if (isset($parsedUrl['query'])) {
			parse_str($parsedUrl['query'], $queryParams);
		}

		// Set up SERVER variables
		$serverParams = [
			'REQUEST_METHOD' => strtoupper($method),
			'REQUEST_URI' => $path . (isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : ''),
			'QUERY_STRING' => $parsedUrl['query'] ?? '',
			'SCRIPT_NAME' => '/index.php',
			'SCRIPT_FILENAME' => '/var/www/html/index.php',
			'PHP_SELF' => '/index.php',
			'HTTP_HOST' => $parsedUrl['host'] ?? 'localhost',
			'SERVER_NAME' => $parsedUrl['host'] ?? 'localhost',
			'SERVER_PORT' => $parsedUrl['port'] ?? '80',
			'HTTPS' => isset($parsedUrl['scheme']) && $parsedUrl['scheme'] === 'https' ? 'on' : 'off'
		];

		// Add headers to SERVER variables
		foreach ($headers as $name => $value) {
			$headerKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
			$serverParams[$headerKey] = $value;

			// Special handling for content type and length
			if (strtolower($name) === 'content-type') {
				$serverParams['CONTENT_TYPE'] = $value;
			} else if (strtolower($name) === 'content-length') {
				$serverParams['CONTENT_LENGTH'] = $value;
			}
		}

		// Set up the request based on method
		switch (strtoupper($method)) {
			case 'GET':
				self::get(array_merge($queryParams, $params));
				break;

			case 'POST':
				self::get($queryParams);
				self::post($params);
				break;

			default:
				// For PUT, DELETE, etc.
				self::get($queryParams);

				// Set raw data if provided
				if (!empty($rawData)) {
					self::rawData($rawData);
				} else if (!empty($params)) {
					// If no raw data but params provided, convert to appropriate format
					$contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? '';

					if (stripos($contentType, 'application/json') !== false) {
						self::rawData(json_encode($params));
					} else {
						// Default to form data
						self::rawData(http_build_query($params));
					}

					// Also set the vars property for Request class
					try {
						$reflection = new ReflectionClass(Request::class);

						if ($reflection->hasProperty('vars')) {
							$property = $reflection->getProperty('vars');
							$property->setAccessible(true);
							$property->setValue(null, $params);
						}
					} catch (ReflectionException $e) {
						// If reflection fails, we can't set the vars
					}
				}
				break;
		}

		// Set server params and files
		self::server($serverParams);
		self::files($files);
	}

	/**
	 * Ensure we're in a testing environment
	 *
	 * @throws SecurityException
	 */
	private static function ensureTestingEnvironment(): void
	{
		// Check for PHPUnit or testing environment
		if (!Application::inTest()) {
			throw new SecurityException('Mock can only be used in testing environments');
		}
	}

	/**
	 * Reset all mocked data to original values
	 *
	 * @return void
	 */
	public static function reset(): void
	{
		if (self::$isMocking) {
			$_GET = self::$original['get'];
			$_POST = self::$original['post'];
			$_SERVER = self::$original['server'];
			$_FILES = self::$original['files'];
			$_COOKIE = self::$original['cookie'];
			$_SESSION = self::$original['session'];

			// Reset any static properties in Request class
			self::resetRequestClass();
		}
	}

	/**
	 * Reset static properties in Request class
	 *
	 * @return void
	 */
	private static function resetRequestClass(): void
	{
		try {
			$reflection = new ReflectionClass(Request::class);

			$staticProps = [
				'realIp' => null,
				'rawData' => null,
				'vars' => null,
				'uploadedFiles' => null,
				'parsedMultipartContent' => null
			];

			foreach ($staticProps as $prop => $defaultValue) {
				if ($reflection->hasProperty($prop)) {
					$property = $reflection->getProperty($prop);
					$property->setAccessible(true);
					$property->setValue(null, $defaultValue);
				}
			}
		} catch (ReflectionException $e) {
			// Silently fail - this is just cleanup
		}
	}

	/**
	 * Start mocking by backing up current superglobals
	 *
	 * @return void
	 * @throws SecurityException
	 */
	public static function start(): void
	{
		self::ensureTestingEnvironment();

		if (!self::$isMocking) {
			// Backup current superglobals

			/** @phpstan-ignore-next-line */
			self::$original['get'] = $_GET ?? [];

			/** @phpstan-ignore-next-line */
			self::$original['post'] = $_POST ?? [];

			/** @phpstan-ignore-next-line */
			self::$original['server'] = $_SERVER ?? [];

			/** @phpstan-ignore-next-line */
			self::$original['files'] = $_FILES ?? [];

			/** @phpstan-ignore-next-line */
			self::$original['cookie'] = $_COOKIE ?? [];

			self::$original['session'] = $_SESSION ?? [];

			self::$isMocking = true;
		}
	}

	/**
	 * Set GET parameters
	 *
	 * @param array $params
	 *
	 * @return void
	 * @throws SecurityException
	 */
	public static function get(array $params): void
	{
		self::ensureTestingEnvironment();

		if (!self::$isMocking) {
			self::start();
		}

		$_GET = $params;
	}

	/**
	 * Set POST parameters
	 *
	 * @param array $params
	 *
	 * @return void
	 * @throws SecurityException
	 */
	public static function post(array $params): void
	{
		self::ensureTestingEnvironment();

		if (!self::$isMocking) {
			self::start();
		}

		$_POST = $params;
	}

	/**
	 * Set raw request data
	 *
	 * @param string $data
	 *
	 * @return void
	 * @throws SecurityException
	 */
	public static function rawData(string $data): void
	{
		self::ensureTestingEnvironment();

		if (!self::$isMocking) {
			self::start();
		}

		try {
			$reflection = new ReflectionClass(Request::class);

			if ($reflection->hasProperty('rawData')) {
				$property = $reflection->getProperty('rawData');
				$property->setAccessible(true);
				$property->setValue(null, $data);
			}
		} catch (ReflectionException $e) {
			// If reflection fails, we can't set the raw data
		}
	}

	/**
	 * Set SERVER parameters
	 *
	 * @param array $params
	 *
	 * @return void
	 * @throws SecurityException
	 */
	public static function server(array $params): void
	{
		self::ensureTestingEnvironment();

		if (!self::$isMocking) {
			self::start();
		}

		$_SERVER = array_merge($_SERVER, $params);
	}

	/**
	 * Set FILES parameters
	 *
	 * @param array $files
	 *
	 * @return void
	 * @throws SecurityException
	 */
	public static function files(array $files): void
	{
		self::ensureTestingEnvironment();

		if (!self::$isMocking) {
			self::start();
		}

		$_FILES = $files;
	}
}
