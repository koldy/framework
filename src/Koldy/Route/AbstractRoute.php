<?php declare(strict_types=1);

namespace Koldy\Route;

use Koldy\Application;
use Koldy\Response\Exception\ServerException;
use Koldy\Response\ResponseExceptionHandler;
use Koldy\Route\Exception as RouteException;
use Koldy\Log;
use Throwable;

/**
 * To create your own routing system, you must extend this class and then,
 * in config/application.php under 'routing_class' set the name of your own class.
 *
 * Routing class must do the following:
 * - parse request URI and determine whats controller, action and parameters
 * - generate proper URLs with controller, action and parameters
 * - handle the error pages
 * - handle the exceptions
 *
 */
abstract class AbstractRoute
{

    /**
     * The URI that is initialized. Do not rely on $_SERVER['REQUEST_URI'].
     *
     * @var string
     */
    protected $uri = null;

    /**
     * The route config defined in config/application.php. This property will be
     * always an array and you should use it as array
     *
     * @var array
     */
    protected $config = null;

    /**
     * Construct the object
     *
     * @param array $config [optional]
     *
     * @example parameter might be "/user/login"
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Prepare everything for HTTP request before executing exec()
     *
     * @param string $uri
     */
    abstract public function prepareHttp(string $uri);

    /**
     * Get the module URL part
     *
     * @return string
     */
    abstract public function getModuleUrl(): string;

    /**
     * Get the controller as it is in URI
     *
     * @return string
     */
    abstract public function getControllerUrl(): string;

    /**
     * What is the controller class name got from URI. When routing class resolves
     * the URI, then you'll must have this info, so, return that name.
     *
     * @return string
     */
    abstract public function getControllerClass(): string;

    /**
     * Get the "action" part as it is URI
     *
     * @return string
     * @example if URI is "/user/login", then this might return "login" only
     */
    abstract public function getActionUrl(): string;

    /**
     * What is the action method name resolved from from URI and request type
     *
     * @return string
     * @example if URI is "/user/show-details/5", then this might return "showDetailsAction"
     */
    abstract public function getActionMethod(): string;

    /**
     * Get the variable from the URL
     *
     * @param mixed $whatVar
     * @param string $default [optional] if variable doesn't exists in request
     *
     * @return mixed
     */
    abstract public function getVar($whatVar, $default = null);

    /**
     * If route knows how to detect language, then override this method.
     * @return string
     * @throws RouteException
     */
    public function getLanguage(): string
    {
        throw new RouteException('Language detection is not implemented');
    }

    /**
     * Is this request Ajax request or not? This is used in \Koldy\Application when printing
     * error or exception
     *
     * @return boolean or false if feature is not implemented
     */
    public function isAjax(): bool
    {
        return false;
    }

    /**
     * Generate link to another page
     *
     * @param string|null $controller
     * @param string|null $action
     * @param array|null $params
     * @param string|null $lang
     *
     * @return string
     */
    abstract public function href(string $controller = null, string $action = null, array $params = null, string $lang = null): string;

    /**
     * Generate link to another page on another server
     *
     * @param string $site
     * @param string|null $controller
     * @param string|null $action
     * @param array|null $params
     * @param string|null $lang
     *
     * @return string
     */
    abstract public function siteHref(string $site, string $controller = null, string $action = null, array $params = null, string $lang = null): string;

    /**
     * Make URL
     *
     * @param string|null $append
     *
     * @return string
     */
    public static function makeUrl(string $append = null): string
    {
        if ($append == null) {
            return 'http' . (Application::isSSL() ? 's' : '') . '://' . Application::getDomain();
        } else {
            if (substr($append, 0, 1) != '/') {
                $append = '/' . $append;
            }
            return 'http' . (Application::isSSL() ? 's' : '') . '://' . Application::getDomain() . $append;
        }
    }

    /**
     * Generate link to the resource file on the same domain
     *
     * @param string $path
     * @param string $assetSite [optional]
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    public function asset(string $path, string $assetSite = null): string
    {
        if (strlen($path) == 0) {
            throw new \InvalidArgumentException('Expected non-empty string');
        }

        // if you pass the full URL that contains "//" part, it'll be immediately
        // returned without any kind of building or parsing

        $pos = strpos($path, '//');
        if (($pos !== false && $pos < 10) || substr($path, 0, 2) == '//') {
            return $path;
        }

        $assets = Application::getConfig('application')->get('assets') ?? [];

        if ($path[0] != '/') {
            $path = '/' . $path;
        }

        $url = null;

        if ($assetSite != null) {
            if (isset($assets[$assetSite])) {
                $url = $assets[$assetSite];
            } else {
                $backtrace = debug_backtrace();
                $caller = $backtrace[1]['function'] ?? '[unknown caller]'; // TODO: Possible fix needed
                Log::warning("Asset site {$assetSite} is used in {$caller}, but not set in application config; using first asset site if any");

                if (count($assets) > 0) {
                    $url = array_values($assets)[0];
                }
            }
        } else {
            if (count($assets) > 0) {
                $url = array_values($assets)[0];
            }
        }

        if ($url == null) {
            return static::makeUrl($path);
        } else {
            if (substr($url, -1) != '/') {
                $url .= '/';
            }

            if (substr($path, 0, 1) == '/') {
                $path = substr($path, 1);
            }

            return $url . $path;
        }
    }

    /**
     * And now, execute the Controller->methodAction() detected in routing class
     * and return stuff, or throw exception, or show error.
     *
     * @return mixed
     */
    abstract public function exec();

    /**
     * If your app throws any kind of exception, it will end up here, so, handle it!
     *
     * @param Throwable $e
     */
    public function handleException(Throwable $e)
    {
        $exceptionHandlerPath = Application::getApplicationPath('controllers/ExceptionHandler.php');

        if (is_file($exceptionHandlerPath)) {
            require_once $exceptionHandlerPath;
            $exceptionHandler = new \ExceptionHandler($e);

            if ($exceptionHandler instanceof ResponseExceptionHandler) {
                $exceptionHandler->exec();
            } else {
                $routeException = new ServerException('Your ExceptionHandler is not instance of ResponseExceptionHandler, can not continue');
                Log::emergency($routeException);
                Application::terminateWithError('Your ExceptionHandler is not instance of ResponseExceptionHandler, can not continue');
            }
        } else {
            $exceptionHandler = new ResponseExceptionHandler($e);
            $exceptionHandler->exec();
        }
    }

}
