<?php declare(strict_types=1);

namespace Koldy;

use Koldy\Application\Exception as ApplicationException;
use Koldy\Request\Exception as RequestException;
use Koldy\Request\UploadedFile;
use Koldy\Response\Exception\BadRequestException;
use stdClass;

/**
 * This is some kind of "wrapper" for $_SERVER. You can fetch some useful
 * information with this class. And it is more robust.
 *
 * We really recommend that you use this class instead of $_SERVER variables directly.
 *
 * If you're looking for a class that is able to make HTTP request, then take a look at \Koldy\Http\Request
 *
 */
class Request
{

	/**
	 * Cache the detected real IP so we don't iterate everything on each call
	 *
	 * @var string|null
	 */
	private static string|null $realIp = null;

	/**
	 * The raw data of the request
	 *
	 * @var string|null
	 */
	private static string|null $rawData = null;

	/**
	 * The variables in case of PUT, DELETE or some other request type
	 *
	 * @var array|null
	 */
	private static array|null $vars = null;

	/**
	 * Local "cache" of requested hosts
	 *
	 * @var array|null
	 */
	private static array|null $hosts = [];

	/**
	 * The once-initialized array of UploadedFile instances; will be empty if there's no files uploaded
	 *
	 * @var UploadedFile[]|null
	 */
	protected static array|null $uploadedFiles = null;

	/**
	 * The once-initialized array of parsed multipart content received through HTTP request
	 *
	 * @var array|null
	 */
	private static array|null $parsedMultipartContent = null;

	/**
	 * Get the real IP address of remote user. If you're looking for server's IP, please refer to Server::ip()
	 *
	 * @return string
	 * @throws Exception
	 * @see \Koldy\Server::ip()
	 */
	public static function ip(): string
	{
		if (static::$realIp !== null) {
			return static::$realIp;
		}

		$possibilities = [
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR'
		];

		foreach ($possibilities as $key) {
			if (isset($_SERVER[$key])) {
				foreach (explode(',', $_SERVER[$key]) as $ip) {
					$ip = trim($ip);

					if (filter_var($ip, FILTER_VALIDATE_IP,
							FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
						static::$realIp = $ip;
						return $ip;
					}
				}
			}
		}

		if (KOLDY_CLI) {
			static::$realIp = '127.0.0.1';
		} else if (isset($_SERVER['REMOTE_ADDR'])) {
			static::$realIp = $_SERVER['REMOTE_ADDR'];
		} else {
			throw new Exception('Unable to detect IP');
		}

		return static::$realIp;
	}

	/**
	 * Get the host name of remote user. This will use gethostbyaddr function or its "cached" version
	 *
	 * @return string|null
	 * @throws Exception
	 * @link http://php.net/manual/en/function.gethostbyaddr.php
	 */
	public static function host(): ?string
	{
		$ip = self::ip();

		if (isset(static::$hosts[$ip])) {
			return static::$hosts[$ip];
		}

		$host = gethostbyaddr($ip);
		static::$hosts[$ip] = ($host === '') ? null : $host;
		return static::$hosts[$ip];
	}

	/**
	 * Are there proxy headers detected?
	 *
	 * @return bool
	 */
	public static function hasProxy(): bool
	{
		return (isset($_SERVER['HTTP_VIA']) || isset($_SERVER['HTTP_X_FORWARDED_FOR']));
	}

	/**
	 * Get proxy signature
	 *
	 * @return string|null
	 * @example 1.1 example.com (squid/3.0.STABLE1)
	 */
	public static function proxySignature(): ?string
	{
		return array_key_exists('HTTP_VIA', $_SERVER) && is_string($_SERVER['HTTP_VIA']) ? $_SERVER['HTTP_VIA'] : null;
	}

	/**
	 * Get remote IP address with additional IP sent over proxy if exists
	 *
	 * @param string $delimiter
	 *
	 * @return string
	 * @throws Exception
	 * @example 89.205.104.23,10.100.10.190
	 */
	public static function ipWithProxy(string $delimiter = ','): string
	{
		$ip = self::ip();

		if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $ip != $_SERVER['HTTP_X_FORWARDED_FOR']) {
			$ip .= "{$delimiter}{$_SERVER['HTTP_X_FORWARDED_FOR']}";
		}

		return $ip;
	}

	/**
	 * Get HTTP VIA header
	 *
	 * @return string|null
	 * @example 1.0 200.63.17.162 (Mikrotik HttpProxy)
	 */
	public static function httpVia(): ?string
	{
		return (isset($_SERVER['HTTP_VIA'])) ? $_SERVER['HTTP_VIA'] : null;
	}

	/**
	 * Get HTTP_X_FORWARDED_FOR header
	 *
	 * @return string|null
	 * @example 58.22.246.105
	 */
	public static function httpXForwardedFor(): ?string
	{
		return (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : null;
	}

	/**
	 * Get the user agent
	 *
	 * @return string|null or null if not set
	 */
	public static function userAgent(): ?string
	{
		return $_SERVER['HTTP_USER_AGENT'] ?? null;
	}

	/**
	 * Get request URI string - this is not necessarily same as Route::getUri()
	 *
	 * @return string|null or null if doesn't exists
	 */
	public static function uri(): ?string
	{
		return $_SERVER['REQUEST_URI'] ?? null;
	}

	/**
	 * Get HTTP referer if set
	 *
	 * @return string|null or null if not set
	 */
	public static function httpReferer(): ?string
	{
		return $_SERVER['HTTP_REFERER'] ?? null;
	}

	/**
	 * Is this GET request?
	 *
	 * @return bool
	 * @throws Exception
	 */
	public static function isGet(): bool
	{
		if (!isset($_SERVER['REQUEST_METHOD'])) {
			throw new RequestException('There is no request method type');
		}

		return $_SERVER['REQUEST_METHOD'] == 'GET';
	}

	/**
	 * Is this POST request?
	 *
	 * @return bool
	 * @throws Exception
	 */
	public static function isPost(): bool
	{
		if (!isset($_SERVER['REQUEST_METHOD'])) {
			throw new RequestException('There is no request method type');
		}

		return $_SERVER['REQUEST_METHOD'] == 'POST';
	}

	/**
	 * Is this PUT request?
	 *
	 * @return bool
	 * @throws Exception
	 */
	public static function isPut(): bool
	{
		if (!isset($_SERVER['REQUEST_METHOD'])) {
			throw new RequestException('There is no request method type');
		}

		return $_SERVER['REQUEST_METHOD'] == 'PUT';
	}

	/**
	 * Is this DELETE request?
	 *
	 * @return bool
	 * @throws Exception
	 */
	public static function isDelete(): bool
	{
		if (!isset($_SERVER['REQUEST_METHOD'])) {
			throw new RequestException('There is no request method type');
		}

		return $_SERVER['REQUEST_METHOD'] == 'DELETE';
	}

	/**
	 * Is this HEAD request?
	 *
	 * @return bool
	 * @throws Exception
	 */
	public static function isHead(): bool
	{
		if (!isset($_SERVER['REQUEST_METHOD'])) {
			throw new RequestException('There is no request method type');
		}

		return $_SERVER['REQUEST_METHOD'] == 'HEAD';
	}

	/**
	 * Is this PATCH request?
	 *
	 * @return bool
	 * @throws Exception
	 */
	public static function isPatch(): bool
	{
		if (!isset($_SERVER['REQUEST_METHOD'])) {
			throw new RequestException('There is no request method type');
		}

		return $_SERVER['REQUEST_METHOD'] == 'PATCH';
	}

	/**
	 * Is this OPTIONS request?
	 *
	 * @return bool
	 * @throws Exception
	 */
	public static function isOptions(): bool
	{
		if (!isset($_SERVER['REQUEST_METHOD'])) {
			throw new RequestException('There is no request method type');
		}

		return $_SERVER['REQUEST_METHOD'] == 'OPTIONS';
	}

	/**
	 * Get request method
	 *
	 * @return string
	 * @throws Exception
	 */
	public static function method(): string
	{
		if (!isset($_SERVER['REQUEST_METHOD'])) {
			throw new RequestException('There is no request method type');
		}

		return $_SERVER['REQUEST_METHOD'];
	}

	/**
	 * Gets the current URL of this request. This is alias of \Koldy\Application::getCurrentURL()
	 *
	 * @return Url
	 * @throws Exception
	 * @see \Koldy\Application::getCurrentURL()
	 */
	public static function getCurrentURL(): Url
	{
		return Application::getCurrentURL();
	}

	/**
	 * Get raw data of the request
	 * @return string
	 * @throws RequestException
	 */
	public static function getRawData(): string
	{
		if (static::$rawData === null) {
			$rawData = file_get_contents('php://input');

			if ($rawData === false) {
				throw new RequestException('Unable to read raw data from request');
			}

			static::$rawData = $rawData;
		}

		return static::$rawData;
	}

	/**
	 * Get the input vars
	 *
	 * @return array
	 * @throws RequestException
	 */
	private static function getInputVars(): array
	{
		if (static::$vars === null) {
			// take those vars only once
			parse_str(static::getRawData(), $vars);
			static::$vars = (array)$vars;
		}

		return static::$vars;
	}

	/**
	 * Get array from raw data posted as JSON
	 *
	 * @return array
	 * @throws Json\Exception
	 * @throws RequestException
	 */
	public static function getDataFromJSON(): array
	{
		return Json::decode(static::getRawData());
	}

	/**
	 * Fetch the value from the resource
	 *
	 * @param string $resourceName
	 * @param string $name parameter name
	 *
	 * @return string|null
	 * @throws RequestException
	 */
	private static function get(string $resourceName, string $name): ?string
	{
		switch ($resourceName) {
			case 'GET':
				if (!isset($_GET)) {
					return null;
				}

				$resource = $_GET;
				break;

			case 'POST':
				if (!isset($_POST)) {
					return null;
				}

				$resource = $_POST;
				break;

			case 'PUT':
			case 'DELETE':
			case 'HEAD':
			case 'PATCH':
			case 'OPTIONS':
				if ($_SERVER['REQUEST_METHOD'] !== $resourceName) {
					return null;
				}

				$resource = static::getInputVars();
				break;

			default:
				throw new RequestException("Invalid resource name={$resourceName}");
		}

		if (array_key_exists($name, $resource)) {
			$value = $resource[$name];

			if (!is_string($value)) {
				throw new RequestException('Fetched resource value for ' . strtoupper($resourceName) . ' is not a string; got ' . gettype($value) . ' instead');
			}

			$value = trim($resource[$name]);

			if ($value == '') {
				return null;
			}

			return $value;
		}

		return null;
	}

	/**
	 * Does GET parameter exists or not
	 *
	 * @param string $name
	 *
	 * @return bool
	 * @link http://koldy.net/docs/input#get
	 */
	public static function hasGetParameter(string $name): bool
	{
		return isset($_GET) && is_array($_GET) && array_key_exists($name, $_GET);
	}

	/**
	 * Returns the GET parameter
	 *
	 * @param string $name
	 *
	 * @return string|null
	 * @throws RequestException
	 * @link http://koldy.net/docs/input#get
	 */
	public static function getGetParameter(string $name): ?string
	{
		return self::get('GET', $name);
	}

	/**
	 * Get all GET parameters
	 *
	 * @return array
	 */
	public static function getAllGetParameters(): array
	{
		return isset($_GET) && is_array($_GET) ? $_GET : [];
	}

	/**
	 * Does POST parameter exists or not
	 *
	 * @param string $name
	 *
	 * @return bool
	 * @link http://koldy.net/docs/input#post
	 */
	public static function hasPostParameter(string $name): bool
	{
		return isset($_POST) && is_array($_POST) && array_key_exists($name, $_POST);
	}

	/**
	 * Returns the POST parameter
	 *
	 * @param string $name
	 *
	 * @return string|null
	 * @throws RequestException
	 * @link http://koldy.net/docs/input#post
	 */
	public static function getPostParameter(string $name): ?string
	{
		return self::get('POST', $name);
	}

	/**
	 * Get all POST parameters
	 *
	 * @return array
	 */
	public static function getAllPostParameters(): array
	{
		return isset($_POST) && is_array($_POST) ? $_POST : [];
	}

	/**
	 * Does PUT parameter exists or not
	 *
	 * @param string $name
	 *
	 * @return bool
	 * @throws RequestException
	 * @link http://koldy.net/docs/input#put
	 */
	public static function hasPutParameter(string $name): bool
	{
		if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
			return array_key_exists($name, static::getInputVars());
		} else {
			return false;
		}
	}

	/**
	 * Returns the PUT parameter
	 *
	 * @param string $name
	 *
	 * @return string|null
	 * @throws RequestException
	 * @link http://koldy.net/docs/input#put
	 */
	public static function getPutParameter(string $name): ?string
	{
		return self::get('PUT', $name);
	}

	/**
	 * @return array
	 * @throws RequestException
	 */
	public static function getAllPutParameters(): array
	{
		return $_SERVER['REQUEST_METHOD'] == 'PUT' ? static::getInputVars() : [];
	}

	/**
	 * Does DELETE parameter exists or not
	 *
	 * @param string $name
	 *
	 * @return bool
	 * @throws RequestException
	 * @link http://koldy.net/docs/input#delete
	 */
	public static function hasDeleteParameter(string $name): bool
	{
		if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
			return array_key_exists($name, static::getInputVars());
		} else {
			return false;
		}
	}

	/**
	 * Returns the DELETE parameter
	 *
	 * @param string $name
	 *
	 * @return string|null
	 * @throws RequestException
	 * @link http://koldy.net/docs/input#delete
	 */
	public static function getDeleteParameter(string $name): ?string
	{
		return self::get('DELETE', $name);
	}

	/**
	 * Get the required parameters. Return bad request if any of them is missing.
	 *
	 * @param string ...$requiredParameters
	 *
	 * @return array
	 * @throws ApplicationException
	 * @throws BadRequestException
	 * @throws RequestException
	 * @link http://koldy.net/docs/input#require
	 *
	 * @example
	 *    $params = Input::requireParams('id', 'email');
	 *    echo $params['email'];
	 */
	public static function requireParams(string ...$requiredParameters): array
	{
		if (KOLDY_CLI) {
			throw new ApplicationException('Unable to require parameters in CLI mode. Check \Koldy\Cli for that');
		}

		switch ($_SERVER['REQUEST_METHOD']) {
			default:
				$parameters = static::getInputVars();
				break;
			case 'GET':
				$parameters = $_GET;
				break;
			case 'POST':
				$parameters = $_POST;
				break;
		}

		$extractedParams = [];
		$missing = [];

		foreach ($requiredParameters as $param) {
			if (array_key_exists($param, $parameters)) {
				$extractedParams[$param] = $parameters[$param];
			} else {
				$missing[] = $param;
			}
		}

		if (count($missing) > 0) {
			$missingParameters = implode(', ', $missing);
			$passedParams = implode(', ', $requiredParameters);
			throw new BadRequestException("Missing {$_SERVER['REQUEST_METHOD']} parameter(s) '{$missingParameters}', only got " . (count($requiredParameters) > 0 ? $passedParams : '[nothing]'));
		}

		return $extractedParams;
	}

	/**
	 * Get required parameters as object
	 *
	 * @param string ...$requiredParameters
	 *
	 * @return stdClass
	 * @throws BadRequestException
	 * @throws Exception
	 *
	 * @example
	 *    $params = Input::requireParams('id', 'email');
	 *    echo $params->email;
	 */
	public static function requireParamsObj(string ...$requiredParameters): stdClass
	{
		$class = new stdClass();

		foreach (static::requireParams(...$requiredParameters) as $param => $value) {
			$class->$param = $value;
		}

		return $class;
	}

	/**
	 * Get all parameters according to request method; if request is made with Content-Type=application/json, it will be
	 * detected and JSON will be parsed. If request contains that header, but the request body is empty, it'll be ignored
	 * and standard Form Data will be taken as parameters data.
	 *
	 * @return array
	 * @throws Exception
	 * @link http://koldy.net/docs/input#all
	 */
	public static function getAllParameters(): array
	{
		if (PHP_SAPI == 'cli') {
			throw new RequestException('There are no parameters in CLI env, you might want to use Cli class instead');
		}

		$method = $_SERVER['REQUEST_METHOD'];

		if ($method === 'GET') {
			return $_GET;
		} else {
			$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? null;

			if ($contentType == 'application/json' && static::getRawData() !== '') {
				// parse raw content
				return static::getDataFromJSON();
			}

			// otherwise:
			if ($method === 'POST') {
				// POST is already parsed by PHP, so return the global $_POST
				return $_POST;
			}

			// otherwise, for PUT, PATCH and DELETE
			return static::getMultipartContent();
		}
	}

	/**
	 * Get all parameters in stdClass
	 *
	 * @return stdClass
	 * @throws Exception
	 */
	public static function getAllParametersObj(): stdClass
	{
		$values = new stdClass();

		foreach (static::getAllParameters() as $name => $value) {
			$values->$name = $value;
		}

		return $values;
	}

	/**
	 * How many parameters are passed?
	 *
	 * @return int
	 * @throws Exception
	 */
	public static function parametersCount(): int
	{
		return count(static::getAllParameters());
	}

	/**
	 * Return true if request contains only parameters from method argument. If there are more parameters than defined,
	 * method will return false.
	 *
	 * @param mixed ...$params
	 *
	 * @return bool
	 * @throws Exception
	 */
	public static function only(...$params): bool
	{
		if (static::parametersCount() != count(is_array($params[0]) ? $params[0] : $params)) {
			return false;
		}

		return static::containsParams(...$params);
	}

	/**
	 * Return true if request contains all of the parameters from method argument. If there are more parameters than
	 * params passed to methods, method will still return true.
	 *
	 * @param mixed ...$params
	 *
	 * @return bool
	 * @throws Exception
	 */
	public static function containsParams(...$params): bool
	{
		$params = array_flip(is_array($params[0]) ? $params[0] : $params);

		foreach (static::getAllParameters() as $name => $value) {
			if (array_key_exists($name, $params)) {
				unset($params[$name]);
			}
		}

		return count($params) == 0;
	}

	/**
	 * Return true only if request doesn't have any of the params from method argument.
	 *
	 * @param array $params
	 *
	 * @return bool
	 * @throws Exception
	 */
	public static function doesntContainParams(...$params): bool
	{
		if (is_array($params[0])) {
			$params = $params[0];
		}

		$targetCount = count($params);
		$params = array_flip($params);

		foreach (static::getAllParameters() as $name => $value) {
			if (array_key_exists($name, $params)) {
				unset($params[$name]);
			}
		}

		return count($params) == $targetCount;
	}

	/**
	 * @param array $uploadedFiles
	 * @param string $property
	 * @param array $data
	 */
	private static function digFile(array &$uploadedFiles, string $property, array $data): void
	{
		foreach ($data as $key => $value) {
			if (is_array($value)) {
				if (!isset($uploadedFiles[$key])) {
					$uploadedFiles[$key] = [];
				}
				static::digFile($uploadedFiles[$key], $property, $value);
			} else {
				if (!isset($uploadedFiles[$key])) {
					$uploadedFiles[$key] = [];
				}

				$uploadedFiles[$key][$property] = $value;
			}
		}
	}

	/**
	 * @param $uploadedFiles
	 *
	 * @throws Security\Exception
	 */
	private static function initUploadedFile(&$uploadedFiles): void
	{
		// check segments of given parameter
		$name = $uploadedFiles['name'] ?? null;
		$type = $uploadedFiles['type'] ?? null;
		$tmpName = $uploadedFiles['tmp_name'] ?? null;
		$error = $uploadedFiles['error'] ?? null;
		$size = $uploadedFiles['size'] ?? null;

		if (is_string($name) && is_string($type) && is_string($tmpName) && is_int($error) && is_int($size)) {
			// yeah! finally...
			$uploadedFiles = new UploadedFile($name, $type, $size, $tmpName, $error);
		} else {
			// dig further...
			foreach ($uploadedFiles as $key => $value) {
				static::initUploadedFile($uploadedFiles[$key]);
			}
		}
	}

	/**
	 * Get the array of all uploaded files by returning array of UploadedFile instances
	 *
	 * @return UploadedFile[]
	 * @throws Exception
	 */
	public static function getAllFiles(): array
	{
		if (static::$uploadedFiles !== null) {
			return static::$uploadedFiles;
		}

		$uploadedFiles = [];

		if (isset($_FILES)) {
			// parse all
			foreach ($_FILES as $field => $file) {
				// first possible case: simple single-file information; it has to have array of 5 elements

				// it probably is simple single-file information
				$name = $file['name'] ?? null;
				$type = $file['type'] ?? null;
				$tmpName = $file['tmp_name'] ?? null;
				$error = $file['error'] ?? null;
				$size = $file['size'] ?? null;

				if (is_string($name) && is_string($type) && is_string($tmpName) && is_int($error) && is_int($size)) {
					// if it's string, then it's easy
					$uploadedFiles[$field] = new UploadedFile($name, $type, $size, $tmpName, $error);
				} else if (is_array($name) && is_array($type) && is_array($tmpName) && is_array($error) && is_array($size)) {
					// ... otherwise, it has nested array(s) for X levels deep

					$uploadedFiles[$field] = [];

					static::digFile($uploadedFiles[$field], 'name', $file['name']);
					static::digFile($uploadedFiles[$field], 'type', $file['type']);
					static::digFile($uploadedFiles[$field], 'tmp_name', $file['tmp_name']);
					static::digFile($uploadedFiles[$field], 'error', $file['error']);
					static::digFile($uploadedFiles[$field], 'size', $file['size']);

					// and now, final cycle.. go through processed array and init instances of UploadedFile
					static::initUploadedFile($uploadedFiles[$field]);
				}
			}
		}

		static::$uploadedFiles = $uploadedFiles;
		return static::$uploadedFiles;
	}

	/**
	 * Gets the multipart content from other request types, such as PATCH, PUT or DELETE
	 *
	 * @return array
	 */
	private static function getMultipartContent(): array
	{
		if (self::$parsedMultipartContent === null) {
			$contentType = array_key_exists('CONTENT_TYPE', $_SERVER) ? $_SERVER['CONTENT_TYPE'] : null;
			$httpContentType = array_key_exists('HTTP_CONTENT_TYPE', $_SERVER) ? $_SERVER['HTTP_CONTENT_TYPE'] : null;
			$default = 'application/x-www-form-urlencoded';
			self::$parsedMultipartContent = Util::parseMultipartContent(file_get_contents('php://input'), $contentType ?? $httpContentType ?? $default);
		}

		return self::$parsedMultipartContent;
	}
}
