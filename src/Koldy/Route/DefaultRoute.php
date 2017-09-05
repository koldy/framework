<?php declare(strict_types = 1);

namespace Koldy\Route;

use Koldy\{
  Application, Log, Response\AbstractResponse, Response\Plain
};
use Koldy\Response\Exception\NotFoundException;

/**
 * I call this the default route because this will be just fine for the most
 * sites in the world. This class will parse and generate the URLs to the
 * following criteria:
 *
 *  http://your.domain.com/[controller]/[action]/[param1]/[param2]/[paramN]
 *  or if module exists under that controller URL:
 *  http://your.domain.com/[module]/[controller]/[action]/[param1]/[param2]/[paramN]
 *
 * Parameters can be caught with getVar() method by passing the position of it.
 *
 * @link http://koldy.net/docs/routes/default-route
 */
class DefaultRoute extends AbstractRoute
{

    /**
     * The resolved module URL part
     *
     * @var string
     */
    protected $moduleUrl = null;

    /**
     * The resolved controller URL part
     *
     * @var string
     * @example if URI is "/users/login", this will be "users"
     */
    protected $controllerUrl = null;

    /**
     * The resolved controller class name
     *
     * @var string
     */
    protected $controllerClass = null;

    /**
     * The controller path
     *
     * @var string
     */
    private $controllerPath = null;

    /**
     * The resolved action url
     *
     * @var string
     */
    protected $actionUrl = null;

    /**
     * The resolved action method name
     *
     * @var string
     */
    protected $actionMethod = null;

    /**
     * Flag if this request is an ajax request maybe?
     *
     * @var boolean
     */
    protected $isAjax = false;

    /**
     * The controller's instance
     *
     * @var object
     */
    protected $controllerInstance = null;

    /**
     * Prepare HTTP before executing exec() method
     *
     * @param string $uri
     *
     * @throws Exception
     * @throws NotFoundException
     */
    public function prepareHttp(string $uri)
    {
        $this->uri = $uri;

        // first, check the URI for duplicate slashes - they are not allowed
        // if you must pass duplicate slashes in URL, then urlencode them
        $redirect = null;
        if (strpos($this->uri, '//') !== false) {
            $redirect = str_replace('//', '/', $this->uri);
        }

        if (strlen($this->uri) > 1 && substr($this->uri, -1) == '/') {
            // ending slash is not needed, unless you have a namespace
            // if you need to pass slash on the end of URI, then urlencode it
            if ($redirect === null) {
                $redirect = substr($this->uri, 0, -1);
            } else {
                $redirect = substr($redirect, 0, -1);
            }
        }

        if ($redirect !== null) {
            header('Location: ' . Application::getDomainWithSchema() . $redirect);
            exit(0);
        }

        $questionPos = strpos($this->uri, '?');
        if ($questionPos !== false) {
            $this->uri = substr($this->uri, 0, $questionPos);
        }

        $ds = DS;

        $this->uri = explode('/', $this->uri);
        if (!isset($this->uri[1])) {
            $this->uri[1] = '';
        }

        // There are two possible scenarios:
        // 1. The first part of URL leads to the module controller
        // 2. The first part of URL leads to the default controller

        if ($this->uri[1] == '') {
            $this->controllerUrl = 'index';
            $this->controllerClass = 'IndexController';
        } else {
            $this->controllerUrl = strtolower($this->uri[1]);
            $this->controllerClass = str_replace(' ', '', ucwords(str_replace(['-', '.'], ' ', $this->controllerUrl))) . 'Controller';
        }

        // Now we have the controller class name detected, but, should it be
        // taken from module or from default controllers?

        $moduleDir = Application::getModulePath($this->controllerUrl);

        if (is_dir($moduleDir)) {
            Application::setCurrentModule($this->controllerUrl);

            // ok, it is a module with module/controller/action path
            $moduleUrl = $this->controllerUrl;
            $this->moduleUrl = $moduleUrl;

            if (isset($this->uri[2]) && $this->uri[2] != '') {
                $this->controllerUrl = strtolower($this->uri[2]);
                $this->controllerClass = str_replace(' ', '', ucwords(str_replace(['-', '.'], ' ', $this->controllerUrl))) . 'Controller';
            } else {
                $this->controllerUrl = 'index';
                $this->controllerClass = 'IndexController';
            }

            $this->controllerPath = "{$moduleDir}controllers{$ds}{$this->controllerClass}.php";
            $mainControllerExists = true;

            if (!is_file($this->controllerPath)) {
                // lets try with default controller when requested one is not here
                $this->controllerPath = Application::getModulePath($moduleUrl) . "controllers{$ds}IndexController.php";

                if (!is_file($this->controllerPath)) {
                    // Even IndexController is missing. Can not resolve that.
                    throw new NotFoundException("Can not find {$this->controllerClass} nor IndexController in {$moduleDir}{$ds}controllers");
                }

                $mainControllerExists = false;
                $this->controllerClass = 'IndexController';
            }

            if ($mainControllerExists) {
                if (!isset($this->uri[3]) || $this->uri[3] == '') {
                    $this->actionUrl = 'index';
                    $this->actionMethod = 'index';
                } else {
                    $this->actionUrl = strtolower($this->uri[3]);
                    $this->actionMethod = ucwords(str_replace(array('-', '.'), ' ', $this->actionUrl));
                    $this->actionMethod = str_replace(' ', '', $this->actionMethod);
                    $this->actionMethod = strtolower(substr($this->actionMethod, 0, 1)) . substr($this->actionMethod, 1);
                }
            } else if (isset($this->uri[2]) && $this->uri[2] != '') {
                $this->actionUrl = strtolower($this->uri[2]);
                $this->actionMethod = ucwords(str_replace(array('-', '.'), ' ', $this->actionUrl));
                $this->actionMethod = str_replace(' ', '', $this->actionMethod);
                $this->actionMethod = strtolower(substr($this->actionMethod, 0, 1)) . substr($this->actionMethod, 1);
            } else {
                $this->actionUrl = 'index';
                $this->actionMethod = 'index';
            }

            // and now, configure the include paths according to the case
            Application::prependIncludePath("{$moduleDir}controllers", "{$moduleDir}library");
        } else {

            // ok, it is the default controller/action
            $this->controllerPath = Application::getApplicationPath("controllers{$ds}{$this->controllerClass}.php");

            $mainControllerExists = true;

            if (!is_file($this->controllerPath)) {
                $this->controllerPath = Application::getApplicationPath("controllers{$ds}IndexController.php");

                if (!is_file($this->controllerPath)) {
                    // Even IndexController is missing. Can not resolve that.
                    throw new NotFoundException("Can not find {$this->controllerClass} nor IndexController in " . Application::getApplicationPath('controllers'));
                }

                $mainControllerExists = false;
                $this->controllerClass = 'IndexController';
            }

            if ($mainControllerExists) {
                if (!isset($this->uri[2]) || $this->uri[2] == '') {
                    $this->actionUrl = 'index';
                    $this->actionMethod = 'index';
                } else {
                    $this->actionUrl = strtolower($this->uri[2]);
                    $this->actionMethod = ucwords(str_replace(['-', '.'], ' ', $this->actionUrl));
                    $this->actionMethod = str_replace(' ', '', $this->actionMethod);
                    $this->actionMethod = strtolower(substr($this->actionMethod, 0, 1)) . substr($this->actionMethod, 1);
                }
            } else {
                $this->actionUrl = strtolower($this->uri[1]);
                $this->actionMethod = ucwords(str_replace(['-', '.'], ' ', $this->actionUrl));
                $this->actionMethod = str_replace(' ', '', $this->actionMethod);
                $this->actionMethod = strtolower(substr($this->actionMethod, 0, 1)) . substr($this->actionMethod, 1);
            }

            // and now, configure the include paths according to the case
            $basePath = Application::getApplicationPath();
            Application::addIncludePath($basePath . 'controllers',    // so you can extend abstract controllers in the same directory if needed,
              $basePath . 'library'      // the place where you can define your own classes and methods
            );
        }

        $this->isAjax = (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'],
              'application/json') !== false);

        $controllerClassName = $this->getControllerClass();

        if (!class_exists($controllerClassName)) {
            throw new Exception("Can not find class {$controllerClassName}; current include path is:\n" . get_include_path());
        }

        $controllerInstance = new $controllerClassName();

        $alwaysRestful = (isset($this->config['always_restful'])) && $this->config['always_restful'] === true;

        if ($alwaysRestful || (property_exists($controllerInstance, 'restful') && $controllerInstance::$restful === true)) {
            // it is restful
            $this->actionMethod = strtolower($_SERVER['REQUEST_METHOD']) . ucfirst($this->actionMethod);

        } else if ($this->isAjax) {
            $this->actionMethod .= 'Ajax';

        } else {
            $this->actionMethod .= 'Action';

        }

        $this->controllerInstance = $controllerInstance;
    }

    /**
     * Is this ajax request or not?
     *
     * @return bool
     */
    public function isAjax(): bool
    {
        return $this->isAjax;
    }

    /**
     * Get the variable value from parameters
     *
     * @param string $whatVar
     * @param string $default
     *
     * @return mixed|null|string
     */
    public function getVar($whatVar, $default = null)
    {
        if (is_numeric($whatVar)) {
            $whatVar = (int)$whatVar + 1;

            if (isset($this->uri[$whatVar])) {
                $value = trim($this->uri[$whatVar]);
                return ($value != '') ? $value : $default;
            } else {
                return $default;
            }
        } else {
            // if variable is string, then treat it like GET parameter
            if (isset($_GET[$whatVar])) {
                $value = trim($_GET[$whatVar]);
                return ($value != '') ? $value : $default;
            } else {
                return $default;
            }
        }
    }

    /**
     * @return string
     */
    public function getModuleUrl(): string
    {
        return $this->moduleUrl;
    }

    /**
     * @return string
     */
    public function getControllerUrl(): string
    {
        return $this->controllerUrl;
    }

    /**
     * @return string
     */
    public function getControllerClass(): string
    {
        return $this->controllerClass;
    }

    /**
     * @return string
     */
    public function getActionUrl(): string
    {
        return $this->actionUrl;
    }

    /**
     * @return string
     */
    public function getActionMethod(): string
    {
        return $this->actionMethod;
    }

    /**
     * @param string $controller
     * @param string|null $action
     * @param array|null $params
     * @param string|null $lang
     *
     * @return string
     */
    public function href(string $controller = null, string $action = null, array $params = null, string $lang = null): string
    {
        return $this->siteHref('', $controller, $action, $params, $lang);
    }

    /**
     * @param string $site
     * @param string|null $controller
     * @param string|null $action
     * @param array|null $params
     * @param string|null $lang
     *
     * @return string
     * @throws \Koldy\Exception
     */
    public function siteHref(string $site, string $controller = null, string $action = null, array $params = null, string $lang = null): string
    {
        if ($controller !== null && strpos($controller, '/') !== false) {
            throw new \InvalidArgumentException('Slash is not allowed in controller name');
        }

        if ($action !== null && strpos($action, '/') !== false) {
            throw new \InvalidArgumentException('Slash is not allowed in action name');
        }

        if ($controller === null) {
            $controller = '';
        }

        if ($site !== '') {
            // we're building link to another server
            $otherSite = Application::getConfig('sites')->get($site);
            if ($otherSite !== null) {
                $url = $otherSite;
            } else {
                Log::warning('Missing sites definition key \'' . $site . '\'; please define it in your sites.php config');
                $url = Application::getDomainWithSchema();
            }
        } else {
            $url = Application::getDomainWithSchema();
        }

        $url .= '/' . $controller;

        if ($action !== null) {
            $url .= '/' . $action;
        }

        if ($params !== null && count($params) > 0) {
            $q = [];
            foreach ($params as $key => $value) {
                if (is_numeric($key)) {
                    $url .= '/' . $value;
                } else {
                    $q[$key] = $value;
                }
            }

            if (sizeof($q) > 0) {
                $url .= '?';
                foreach ($q as $key => $value) {
                    $url .= "{$key}={$value}&";
                }
                $url = substr($url, 0, -1);
            }
        }

        return $url;
    }

    /**
     * @return mixed
     * @throws NotFoundException
     */
    public function exec()
    {
        if (method_exists($this->controllerInstance, 'before')) {
            $response = $this->controllerInstance->before();
            // if "before" method returns anything, then we should not continue, and after will not be executed
            if ($response !== null) {
                return $response;
            }
        }

        $method = $this->getActionMethod();
        if (method_exists($this->controllerInstance, $method) || method_exists($this->controllerInstance, '__call')) {
            // get the return value of your method (json, xml, view object, download, string or nothing)
            $output = $this->controllerInstance->$method();

            if (method_exists($this->controllerInstance, 'after')) {
                if (!($output instanceof AbstractResponse)) {
                    $output = Plain::create((string) $output);
                }

                $controllerInstance = $this->controllerInstance;

                // call after() method defined in controller by attaching it to response object and executing it after connection has been closed
                $output->after(function () use ($controllerInstance, $output) {
                    $controllerInstance->after($output);
                });

                return $output;
            } else {
                return $output;
            }

        } else {
            // the method we need doesn't exists, so, there is nothing we can do about it any more
            throw new NotFoundException("Can not find method={$method} in class={$this->getControllerClass()} on path={$this->controllerPath} for URI=" . Application::getUri());
        }
    }

}
