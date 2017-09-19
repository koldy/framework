<?php declare(strict_types = 1);

namespace Koldy;

use Koldy\Application\Exception as ApplicationException;
use Koldy\Request\Exception as RequestException;
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
     * @var string
     */
    private static $realIp = null;

    /**
     * The raw data of the request
     *
     * @var string
     */
    private static $rawData = null;

    /**
     * The variables in case of PUT, DELETE or some other request type
     *
     * @var array
     */
    private static $vars = null;

    /**
     * Local "cache" of requested hosts
     *
     * @var array
     */
    private static $hosts = [];

    /**
     * Get the real IP address of remote user. If you're looking for server's IP, please refer to Server::ip()
     *
     * @return string
     * @throws Exception
     * @see Server::ip()
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

                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
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
        if (isset($_SERVER) && isset($_SERVER['HTTP_VIA'])) {
            return $_SERVER['HTTP_VIA'];
        }

        return null;
    }

    /**
     * Get the IP address of proxy server if exists
     *
     * @return string|null
     * @deprecated in favor of httpXForwadedFor()
     */
    public static function proxyForwardedFor(): ?string
    {
        if (isset($_SERVER) && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        return null;
    }

    /**
     * Get remote IP address with additional IP sent over proxy if exists
     *
     * @param string $delimiter
     *
     * @return string
     * @example 89.205.104.23,10.100.10.190
     */
    public static function ipWithProxy($delimiter = ','): string
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
     * @return string or null if not set
     */
    public static function userAgent(): ?string
    {
        return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
    }

    /**
     * Get request URI string
     *
     * @return string or null if doesn't exists
     */
    public static function uri(): ?string
    {
        return isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null;
    }

    /**
     * Get HTTP referer if set
     *
     * @return string or null if not set
     */
    public static function httpReferer(): ?string
    {
        return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
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
     * @see Application::getCurrentURL()
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
            static::$rawData = file_get_contents('php://input');

            if (static::$rawData === false) {
                throw new RequestException('Unable to read raw data from request');
            }
        }

        return static::$rawData;
    }

    /**
     * Get the input vars
     *
     * @return array
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
     * @param string $default [optional] default value if parameter doesn't exists
     * @param array $allowedValues [optional] allowed values; if resource value doesn't contain one of values in this array, default is returned
     *
     * @return string
     * @throws Exception
     */
    private static function get(string $resourceName, string $name, string $default = null, array $allowedValues = null)
    {
        switch ($resourceName) {
            case 'GET':
                $resource = $_GET;
                break;

            case 'POST':
                if (!isset($_POST)) {
                    return $default;
                }

                $resource = $_POST;
                break;

            case 'PUT':
                if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
                    return $default;
                }

                $resource = static::getInputVars();
                break;

            case 'DELETE':
                if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
                    return $default;
                }

                $resource = static::getInputVars();
                break;

            default:
                throw new RequestException("Invalid resource name={$resourceName}");
                break;
        }

        if (array_key_exists($name, $resource)) {

            if (is_array($resource[$name])) {
                return $resource[$name];
            }

            $value = trim($resource[$name]);

            if ($value == '') {
                return $default;
            }

            if ($allowedValues !== null) {
                return (in_array($value, $allowedValues)) ? $value : $default;
            }

            return $value;
        } else {
            return $default;
        }
    }

    /**
     * Does GET parameter exists or not
     *
     * @param string $name
     *
     * @return bool
     * @link http://koldy.net/docs/input#get
     */
    public static function hasGetParameter($name)
    {
        return isset($_GET) && isset($_GET[$name]);
    }

    /**
     * Returns the GET parameter
     *
     * @param string $name
     * @param string $default [optional]
     * @param array $allowed [optional]
     *
     * @return string
     * @link http://koldy.net/docs/input#get
     */
    public static function getGetParameter(string $name, string $default = null, array $allowed = null): string
    {
        return self::get('GET', $name, $default, $allowed);
    }

    /**
     * Get all GET parameters
     *
     * @return array
     */
    public static function getAllGetParameters(): array
    {
        return isset($_GET) ? $_GET : [];
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
        return isset($_POST) && array_key_exists($name, $_POST);
    }

    /**
     * Returns the POST parameter
     *
     * @param string $name
     * @param string $default [optional] default NULL
     * @param array $allowed [optional]
     *
     * @return string
     * @link http://koldy.net/docs/input#post
     */
    public static function getPostParameter(string $name, string $default = null, array $allowed = null): string
    {
        return self::get('POST', $name, $default, $allowed);
    }

    /**
     * Get all POST parameters
     *
     * @return array
     */
    public static function getAllPostParameters(): array
    {
        return isset($_POST) ? $_POST : [];
    }

    /**
     * Does PUT parameter exists or not
     *
     * @param string $name
     *
     * @return bool
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
     * @param string $default [optional]
     * @param array $allowed [optional]
     *
     * @return string
     * @link http://koldy.net/docs/input#put
     */
    public static function getPutParameter(string $name, string $default = null, array $allowed = null): string
    {
        return self::get('PUT', $name, $default, $allowed);
    }

    /**
     * @return array
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
     * @param string $default [optional]
     * @param array $allowed [optional]
     *
     * @return string
     * @link http://koldy.net/docs/input#delete
     */
    public static function getDeleteParameter(string $name, $default = null, array $allowed = null): string
    {
        return self::get('DELETE', $name, $default, $allowed);
    }

    /**
     * Get the required parameters. Return bad request if any of them is missing.
     *
     * @param string[] ...$requiredParameters
     *
     * @return array
     * @throws BadRequestException
     * @throws Exception
     * @link http://koldy.net/docs/input#require
     *
     * @example
     *    $params = Input::requireParams('id', 'email');
     *    echo $params->email;
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
     * @param \string[] ...$requiredParameters
     *
     * @return stdClass
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
     * Get all parameters according to request method
     * @return array
     * @throws Exception
     * @link http://koldy.net/docs/input#all
     */
    public static function getAllParameters(): array
    {
        if (PHP_SAPI == 'cli') {
            throw new RequestException('There are no parameters in CLI env, you might want to use Cli class instead');
        }

        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                return $_GET;
                break;

            case 'POST':
                return $_POST;
                break;

            case 'PUT':
            case 'DELETE':
            default:
                return static::getInputVars();
                break;
        }
    }

    /**
     * Get all parameters in stdClass
     *
     * @return stdClass
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
     */
    public static function parametersCount(): int
    {
        return count(static::getAllParameters());
    }

    /**
     * Return true if request contains only parameters from method argument. If there are more parameters then defined,
     * method will return false.
     *
     * @param mixed ...$params
     *
     * @return bool
     */
    public static function only(...$params): bool
    {
        if (static::parametersCount() != count(is_array($params[0]) ? $params[0] : $params)) {
            return false;
        }

        return static::containsParams(...$params);
    }

    /**
     * Return true if request contains all of the parameters from method argument. If there are more parameters then
     * params passed to methods, method will still return true.
     *
     * @param mixed ...$params
     *
     * @return bool
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

}
