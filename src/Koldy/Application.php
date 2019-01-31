<?php declare(strict_types=1);

namespace Koldy;

use Koldy\Application\Exception as ApplicationException;
use Koldy\Config\Exception as ConfigException;
use Koldy\Cli\Exception as CliException;
use Koldy\Response\AbstractResponse;
use Koldy\Route\AbstractRoute;
use Throwable;

/**
 * The main class of Koldy framework. This class will bootstrap the request. It
 * will prepare everything you need and it will print response to the request by the
 * way you want.
 *
 * Enjoy!
 *
 * @link http://koldy.net/docs/how-framework-works
 */
class Application
{

    const DEVELOPMENT = 0;
    const PRODUCTION = 1;
    const TEST = 2;

    /**
     * All loaded configs in one place so feel free to call
     * Application::getConfig() as many times as you want
     *
     * @var Config[]
     */
    protected static $configs = [];

    /**
     * Directory in which all configs are stored, with ending slash
     *
     * @var string
     */
    protected static $configsPath = null;

    /**
     * The array of already registered modules, so we don't execute code if you already registered your module
     *
     * @var array
     */
    private static $registeredModules = [];

    /**
     * Current running module, if any
     *
     * @var string
     */
    private static $currentModule = null;

    /**
     * The application environment mode
     *
     * @var int
     */
    protected static $env = self::DEVELOPMENT;

    /**
     * Is this application on live server or not? So you can do combinations:
     *
     * - production/live
     * - production/not-live (testing production on developer machine or anywhere else)
     * - development/not-live (real dev mode)
     * - development/live - (running live, but in dev mode; e.g. you want your analytics, but serving dev not minimized versions)
     * - test/not-live - should be the only combination with test env
     *
     * @var bool
     */
    protected static $isLive = false;

    /**
     * Thr routing class instance - this is the instance of class
     * defined in config/application.php under routing_class
     *
     * @var \Koldy\Route\AbstractRoute
     */
    protected static $routing = null;

    /**
     * @var Url
     */
    private static $currentUrl = null;

    /**
     * The requested URI. Basically $_SERVER['REQUEST_URI'], but not if you pass your own
     *
     * @var string
     */
    protected static $uri = null;

    /**
     * If CLI env, then this is the path of CLI script
     *
     * @var string
     */
    protected static $cliScriptPath = null;

    /**
     * The parameter from CLI call - the script name
     *
     * @var string
     */
    protected static $cliName = null;

    /**
     * Current domain on which we're running
     *
     * @var string
     */
    protected static $domain = null;

    /**
     * Are we behind SSL?
     *
     * @var bool
     */
    protected static $isSSL = false;

    /**
     * Path to application folder, with ending SLASH
     *
     * @var string
     */
    private static $applicationPath = null;

    /**
     * Path to module folder, with ending SLASH
     *
     * @var string
     */
    private static $modulePath = null;

    /**
     * Path to storage folder, with ending SLASH
     *
     * @var string
     */
    private static $storagePath = null;

    /**
     * Path to view folder, with ending SLASH
     *
     * @var string
     */
    private static $viewPath = null;

    /**
     * Path to scripts folder, with ending SLASH
     *
     * @var string
     */
    private static $scriptsPath = null;

    /**
     * Path to public folder, with ending SLASH
     *
     * @var string
     */
    private static $publicPath = null;

    /**
     * Terminate execution immediately - use it when there's no other way of recovering from error, usually
     * in boot procedure, when exceptions are not loaded yet and etc.
     *
     * Some parts of framework use this method, that's why it's public. You should never get into case when
     * using this method would be recommended.
     *
     * @param string $message
     * @param int $errorCode
     */
    public static function terminateWithError(string $message, int $errorCode = 503): void
    {
        http_response_code($errorCode);
        header('Retry-After: 300'); // 300 seconds / 5 minutes
        print $message;
        exit(1);
    }

    /**
     * Calling Koldy\Autoloader::register() will just register framework itself to be used as library. Calling this
     * autoload() method will allow controllers and other root clases to load properly
     *
     * @param string $className
     *
     * @throws ApplicationException
     */
    public static function autoload(string $className): void
    {
        $classPath = str_replace('\\', DS, $className);
        $path = $classPath . '.php';

        if ((@include $path) === false) {
            // if we fail to include a file, let's throw an exception so we can see where it happened - it'll speed up troubleshooting
            $includePath = get_include_path();
            throw new ApplicationException("Unable to load class {$className} in current include path={$includePath}");
        }
    }

    /**
     * Use the application config file and validate all values we need
     *
     * @param string|array $data if string, then it can be path relative to your index.php file, otherwise it's the
     * content of configs/application.php
     *
     * @throws Exception
     */
    public static function useConfig($data): void
    {
        defined('KOLDY_START') || define('KOLDY_START', microtime(true));
        defined('KOLDY_CLI') || define('KOLDY_CLI', PHP_SAPI == 'cli');
        defined('DS') || define('DS', DIRECTORY_SEPARATOR); // this is just shorthand for Directory Separator

        if (!is_string($data) && !is_array($data)) {
            static::terminateWithError('Can not start application, expected array or string (path to config file) when useConfig is called; got ' . gettype($data));
        }

        $configInstance = new Config('application');

        if (is_string($data)) {
            $applicationConfigPath = stream_resolve_include_path($data); // this is path to /application/configs/application.php

            if ($applicationConfigPath === false || !is_file($applicationConfigPath)) {
                static::terminateWithError('Can not resolve the full path to the main application config file or file doesn\'t exists!');
            }

            try {
                $configInstance->loadFrom($applicationConfigPath);
            } catch (ConfigException $e) {
                static::terminateWithError($e->getMessage());
            }
        } else {
            $configInstance->setData($data);
        }

        static::$publicPath = dirname($_SERVER['SCRIPT_FILENAME']) . '/';

        // now, let's examine configuration we got - it's getting more strict now
        try {
            $siteUrl = $configInstance->get('site_url');

            if ($siteUrl == null) {
                static::$domain = $_SERVER['HTTP_HOST'] ?? null;
                static::$isSSL = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off';

            } else if (is_string($siteUrl)) {
                $siteUrl = explode('//', $siteUrl);

                if (count($siteUrl) != 2) {
                    throw new ConfigException('Defined site_url in application config must contain double slash');
                }

                static::$domain = $siteUrl[1];
                static::$isSSL = substr($siteUrl[0], 0, 6) == 'https:';

            } else if (is_array($siteUrl)) {
                if (count($siteUrl) === 0) {
                    throw new ConfigException('If site_url is defined as array, then it must be non-empty array');
                }

                if (isset($_SERVER['HTTP_HOST'])) {
                    foreach ($siteUrl as $domainWithSchema) {
                        $doubleSlashPosition = strpos($domainWithSchema, '//');

                        if ($doubleSlashPosition === false) {
                            throw new ConfigException('Every defined site_url in application config must contain double slash (//)');
                        }

                        $domain = substr($domainWithSchema, $doubleSlashPosition + 2);

                        if ($domain == $_SERVER['HTTP_HOST']) {

                            static::$domain = $domain;
                            static::$isSSL = substr($domainWithSchema, 0, 6) == 'https:';
                        }
                    }
                } else {
                    // we are probably in CLI mode, so let's take the first value from 'site_url' config
                    $domainWithSchema = $siteUrl[0];

                    $doubleSlashPosition = strpos($domainWithSchema, '//');

                    if ($doubleSlashPosition === false) {
                        throw new ConfigException('Every defined site_url in application config must contain double slash (//)');
                    }

                    $domain = substr($domainWithSchema, $doubleSlashPosition + 2);

                    static::$domain = $domain;
                    static::$isSSL = substr($domainWithSchema, 0, 6) == 'https:';
                }

                // beware then static::$domain might stay null, in this case, in run(), redirect to first domain if site_url is array
            }
        } catch (ConfigException $e) {
            static::terminateWithError($e->getMessage());
        }


        $paths = $configInstance->get('paths');

        if (!is_array($paths)) {
            static::terminateWithError('Application config doesn\'t have paths array defined');
        }

        ///// APPLICATION PATH /////
        if (!isset($paths['application'])) {
            static::terminateWithError('Path to \'application\' in \'paths\' is not defined');
        } else if (substr($paths['application'], -1) != '/') {
            static::terminateWithError('Path to \'application\' in \'paths\' must end with slash (/)');
        }

        static::$applicationPath = $paths['application'];


        ///// STORAGE PATH /////
        if (!isset($paths['storage'])) {
            static::terminateWithError('Path to \'storage\' in \'paths\' is not defined');
        } else if (substr($paths['storage'], -1) != '/') {
            static::terminateWithError('Path to \'storage\' in \'paths\' must end with slash (/)');
        }

        static::$storagePath = $paths['storage'];


        ///// VIEW PATH /////
        if (isset($paths['view']) && substr($paths['storage'], -1) != '/') {
            static::terminateWithError('Path to \'storage\' in \'paths\' must end with slash (/)');
        }

        static::$viewPath = $paths['view'] ?? (static::$applicationPath . 'views/');


        ///// MODULES PATH /////
        if (isset($paths['modules']) && substr($paths['modules'], -1) != '/') {
            static::terminateWithError('Path to \'modules\' in \'paths\' must end with slash (/)');
        }

        static::$modulePath = $paths['modules'] ?? (static::$applicationPath . 'modules/');


        ///// CONFIGS PATH /////
        if (isset($paths['configs']) && substr($paths['configs'], -1) != '/') {
            static::terminateWithError('Path to \'configs\' in \'paths\' must end with slash (/)');
        }

        static::$configsPath = $paths['configs'] ?? (static::$applicationPath . 'configs/');


        ///// SCRIPTS PATH /////
        if (isset($paths['scripts']) && substr($paths['scripts'], -1) != '/') {
            static::terminateWithError('Path to \'scripts\' in \'paths\' must end with slash (/)');
        }

        static::$scriptsPath = $paths['scripts'] ?? (static::$applicationPath . 'scripts/');


        // check the environment
        $env = $configInstance->get('env');
        if ($env === null || !($env == static::DEVELOPMENT || $env == static::PRODUCTION || $env == static::TEST)) {
            static::terminateWithError('Invalid ENV parameter in main application config');
        }
        static::$env = $env;

        $key = $configInstance->get('key');
        if (!isset($key) || !is_string($key) || strlen($key) > 32) {
            static::terminateWithError('Invalid unique key in main application config. It has to be max 32 chars long.');
        }

        $timezone = $configInstance->get('timezone');
        if (!isset($timezone)) {
            static::terminateWithError('Timezone is not set in main application config');
        }

        static::$isLive = $configInstance->get('live') === true;
        static::$configs['application'] = $configInstance;
    }

    /**
     * Add additional include path(s) - add anything you want under include path
     *
     * @param array ...$path
     */
    public static function addIncludePath(...$path): void
    {
        $paths = explode(PATH_SEPARATOR, get_include_path());

        foreach ($path as $r) {

            if (is_array($r)) {
                foreach ($r as $t) {
                    $paths[] = $t;
                }
            } else {
                $paths[] = $r;
            }
        }

        set_include_path(implode(PATH_SEPARATOR, array_unique($paths)));
    }

    /**
     * Add additional include path(s) - add anything you want under include path
     *
     * @param array ...$path
     */
    public static function prependIncludePath(...$path): void
    {
        $finalPaths = [];
        foreach ($path as $r) {

            if (is_array($r)) {
                foreach ($r as $t) {
                    $finalPaths[] = $t;
                }
            } else {
                $finalPaths[] = $r;
            }
        }

        foreach (explode(PATH_SEPARATOR, get_include_path()) as $r) {
            $finalPaths[] = $r;
        }

        set_include_path(implode(PATH_SEPARATOR, array_unique($finalPaths)));
    }

    /**
     * Get the path to application folder with ending slash
     *
     * @param string $append [optional] append anything you want to application path
     *
     * @return string
     * @example /var/www/your.site/com/application/
     */
    public static function getApplicationPath(string $append = null): string
    {
        if ($append === null) {
            return static::$applicationPath;
        } else {
            return str_replace(DS . DS, DS, static::$applicationPath . $append);
        }
    }

    /**
     * Get the path to storage folder with ending slash
     *
     * @param string $append [optional] append anything you want to application path
     *
     * @return string
     * @example /var/www/your.site/com/storage/
     */
    public static function getStoragePath(string $append = null): string
    {
        if ($append === null) {
            return static::$storagePath;
        } else {
            return str_replace(DS . DS, DS, static::$storagePath . $append);
        }
    }

    /**
     * Get the path to the public folder with ending slash
     *
     * @param string $append [optional] append anything you want to application path
     *
     * @return string
     * @example /var/www/your.site/com/public/
     */
    public static function getPublicPath(string $append = null): string
    {
        if ($append === null) {
            return static::$publicPath;
        } else {
            return str_replace(DS . DS, DS, static::$publicPath . $append);
        }
    }

    /**
     * Get the path to directory with views
     *
     * @param string|null $append
     *
     * @return string
     */
    public static function getViewPath(string $append = null): string
    {
        if ($append === null) {
            return static::$viewPath;
        } else {
            return str_replace(DS . DS, DS, static::$viewPath . $append);
        }
    }

    /**
     * Get the running CLI script name - this is available only if this
     * request is running in CLI environment
     *
     * @return string
     * @example if you call "php cli.php backup", this method will return "/path/to/application/scripts/backup.php"
     */
    public static function getCliScriptPath(): string
    {
        return static::$cliScriptPath;
    }

    /**
     * Get the CLI script name
     *
     * @return string
     * @example if you call "php cli.php backup", this method will return "backup" only
     */
    public static function getCliName(): string
    {
        return static::$cliName;
    }

    /**
     * Is this CLI request?
     * @return bool
     */
    public static function isCli(): bool
    {
        return static::$cliName !== null;
    }

    /**
     * @return bool
     */
    public static function isSSL(): bool
    {
        return static::$isSSL;
    }

    /**
     * Get the configs from any config file, fetched by config name. Config name is the name on file system,
     * so you can fetch Koldy's config files or your own configs.
     *
     * @param string $name
     * @param bool $isPointerConfig
     *
     * @return Config
     * @throws Exception
     */
    public static function getConfig(string $name, bool $isPointerConfig = false): Config
    {
        if (isset(static::$configs[$name])) {
            return static::$configs[$name];
        }

	    // otherwise, lookup for config on file system
	    $applicationConfig = static::$configs['application'] ?? null;

	    if ($applicationConfig === null) {
		    throw new ConfigException('The main "application" config is NOT passed to the web app so framework can\'t load other configs because it doesn\'t know where they are');
	    }

	    $configFiles = $applicationConfig->get('config', []);

        $config = new Config($name, $isPointerConfig);

        if (array_key_exists($name, $configFiles)) {
            $path = stream_resolve_include_path($configFiles[$name]);
        } else {
            $path = static::$configsPath . $name . '.php';
        }

        $config->loadFrom($path);
        static::$configs[$name] = $config;
        return $config;
    }

    /**
     * @param Config $config
     *
     * @throws ConfigException
     */
    public static function setConfig(Config $config): void
    {
        if ($config->name() == 'application') {
            throw new ConfigException('Can\'t set config under \'application\' name; name \'application\' is not permitted because it shouldn\'t be possible to change those settings in runtime');
        }

        static::$configs[$config->name()] = $config;
    }

    /**
     * @param string $name
     *
     * @throws ApplicationException
     */
    public static function removeConfig(string $name): void
    {
        if ($name == 'application') {
            throw new ApplicationException('You\'re not allowed to remove \'application\' config in runtime');
        }

        if (isset(static::$configs[$name])) {
            unset(static::$configs[$name]);
        }
    }

    /**
     * Is there a config under given name
     *
     * @param string $name
     *
     * @return bool
     */
    public static function hasConfig(string $name): bool
    {
        return isset(static::$configs[$name]);
    }

    /**
     * Reload all configs that were loaded from file system
     */
    public static function reloadConfig(): void
    {
        foreach (static::$configs as $config) {
            $config->reload();
        }
    }

    /**
     * Get application current detected domain
     *
     * @return string
     * @throws Exception
     */
    public static function getDomain(): string
    {
        if (static::$domain == null) {
            throw new ApplicationException('Can not get domain when domain is not set; check site_url in application config');
        }

        return static::$domain;
    }

    /**
     * Get the application URI. Yes, use this one instead of $_SERVER['REQUEST_URI']
     * because you can pass this URI in index.php while calling Application::run()
     * or somehow different so the real request URI will be overridden.
     *
     * @return string
     * @throws ApplicationException
     */
    public static function getUri(): string
    {
        if (static::$uri == null) {
            throw new ApplicationException('Can not get URI when URI is not set; check site_url in application config');
        }

        return static::$uri;
    }

    /**
     * Get full domain with schema
     *
     * @return string
     * @throws Exception
     */
    public static function getDomainWithSchema(): string
    {
        return 'http' . (static::isSSL() ? 's' : '') . '://' . static::getDomain();
    }

    /**
     * Get full current URL, with schema
     *
     * @return Url
     * @throws Exception
     */
    public static function getCurrentURL(): Url
    {
        if (static::$currentUrl instanceof Url) {
            return static::$currentUrl;
        }

        if (Application::isCli()) {
            throw new ApplicationException('Can not get current URL while running in CLI mode; URL doesn\'t exist in CLI mode');
        }

        static::$currentUrl = new Url(static::getDomainWithSchema() . static::getUri());
        return static::$currentUrl;
    }

    /**
     * Get the initialized routing class
     *
     * @return \Koldy\Route\AbstractRoute
     * @throws Exception
     */
    public static function route(): AbstractRoute
    {
        if (static::$routing === null) {
            $config = static::getConfig('application');
            $routingClassName = $config->get('routing_class');
            $routeOptions = $config->get('routing_options') ?? [];

            if ($routingClassName == null) {
                static::terminateWithError('Can not init routing class when routing_class is not set in application config');
            }

            static::$routing = new $routingClassName($routeOptions);
        }

        return static::$routing;
    }

    /**
     * Is application running in development mode or not
     *
     * @return bool
     */
    public static function inDevelopment(): bool
    {
        return static::$env === self::DEVELOPMENT;
    }

    /**
     * Is application running in production mode or not
     *
     * @return boolean
     */
    public static function inProduction(): bool
    {
        return static::$env === self::PRODUCTION;
    }

    /**
     * Is application running in test mode or not
     *
     * @return boolean
     */
    public static function inTest(): bool
    {
        return static::$env === self::TEST;
    }

    /**
     * Is app running live or not?
     *
     * @return bool
     */
    public static function isLive(): bool
    {
        return static::$isLive;
    }

    /**
     * Register module by registering include path and by running init.php in module root folder
     *
     * @param string $name
     *
     * @example if your module is located on "/application/modules/invoices", then pass "invoices"
     */
    public static function registerModule(string $name): void
    {
        if (!isset(static::$registeredModules[$name])) {
            $modulePath = static::getModulePath($name);

            static::prependIncludePath($modulePath . 'controllers', $modulePath . 'library');
            static::$registeredModules[$name] = true;
            $initPath = $modulePath . 'init.php';

            if (is_file($initPath)) {
                require $initPath;
            }
        }
    }

    /**
     * @param string $module
     *
     * @throws ApplicationException
     * @throws Exception
     */
    public static function setCurrentModule(string $module): void
    {
        if (static::$currentModule !== null) {
            $currentModule = static::$currentModule;
            throw new ApplicationException("Current module was already set to {$currentModule}; once current module is set, you can't change it");
        }

        static::$currentModule = $module;

        // register current module & run init.php if present
        static::registerModule($module);
    }

    /**
     * @return null|string
     */
    public static function getCurrentModule(): ?string
    {
        return static::$currentModule;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public static function isCurrentModule(string $name): bool
    {
        return static::$currentModule === $name;
    }

    /**
     * Get the path on file system to the module WITH ending slash
     *
     * @param string $name
     *
     * @return string
     */
    public static function getModulePath($name): string
    {
        $modulePath = static::$modulePath;
        return str_replace(DS . DS, DS, $modulePath . DS . $name . DS);
    }

    /**
     * Is module with given name already registered in system or not
     *
     * @param string $name
     *
     * @return bool
     */
    public static function isModuleRegistered($name): bool
    {
        return isset(static::$registeredModules[$name]);
    }

    /**
     * Get the request execution time in milliseconds
     *
     * @return float
     */
    public static function getRequestExecutionTime(): float
    {
        return round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2);
    }

    /**
     * Get the key defined in application config
     *
     * @return string
     * @throws Exception
     */
    public static function getKey(): string
    {
        $key = static::getConfig('application')->get('key');

        if ($key === null || $key === '') {
            throw new ApplicationException('The key \'key\' in application config is invalid; please set non-empty string there');
        }

        if ($key == '_____ENTERSomeRandomKeyHere_____') {
            throw new ApplicationException('Please configure key \'key\' in main application config, it can\'t be _____ENTERSomeRandomKeyHere_____');
        }

        if (strlen($key) > 32) {
            throw new ApplicationException('Please make sure your application key in application config is not longer then 32 chars');
        }

        return $key;
    }

    /**
     * Gets default application encoding set in mandatory config under "encoding" key. If not set, it'll return "UTF-8" as default.
     * Encoding will be used in functions list mb_strlen()
     *
     * @return string
     * @throws Exception
     *
     * @link http://www.php.net/manual/en/mbstring.supported-encodings.php
     */
    public static function getEncoding(): string
    {
	    if (static::hasConfig('application')) {
		    return static::getConfig('application')->get('encoding', 'UTF-8');
	    } else {
		    return 'UTF-8';
	    }
    }

    /**
     * Initialize app
     *
     * @param string|null $uri
     * @throws ConfigException
     * @throws Exception
     */
    public static function init(string $uri = null): void
    {
        if (!defined('KOLDY_CLI')) {
            // KOLDY_CLI is not defined, which probably means useConfig() wasn't called
            static::terminateWithError('Don\'t call \Koldy\Application::run() before calling useConfig(). Check the documentation');
        }

        spl_autoload_register('\Koldy\Application::autoload');

        // set the error reporting in development mode
        if (static::inDevelopment()) {
            error_reporting(-1);
        }

        $config = static::getConfig('application');

        date_default_timezone_set($config->get('timezone', 'UTC'));

        $includePaths = [];
        $basePath = static::getApplicationPath();

        // register include path of application itself
        $includePaths[] = $basePath . 'controllers';

        if (is_dir($basePath . 'library')) {
            $includePaths[] = $basePath . 'library';
        }

        // adding additional include paths if there are any
        $additionalIncludePath = $config->get('additional_include_path') ?? [];

        // set the include path
        static::addIncludePath($includePaths, $additionalIncludePath);

        // set the error handler
        set_error_handler(function ($errorNumber, $errorMessage, $errorFile, $errorLine) {
            if (!(error_reporting() & $errorNumber)) {
                // This error code is not included in error_reporting
                return;
            }

            switch ($errorNumber) {
                case E_USER_ERROR:
                    $logMessage = new \Koldy\Log\Message('error');
                    $logMessage->addPHPErrorMessage($errorMessage, $errorFile, $errorNumber, $errorLine);
                    Log::message($logMessage);
                    break;

                case E_USER_WARNING:
                case E_DEPRECATED:
                case E_STRICT:
                    $logMessage = new \Koldy\Log\Message('warning');
                    $logMessage->addPHPErrorMessage($errorMessage, $errorFile, $errorNumber, $errorLine);
                    Log::message($logMessage);
                    break;

                case E_USER_NOTICE:
                    $logMessage = new \Koldy\Log\Message('notice');
                    $logMessage->addPHPErrorMessage($errorMessage, $errorFile, $errorNumber, $errorLine);
                    Log::message($logMessage);
                    break;


                default:
                    $logMessage = new \Koldy\Log\Message('warning');
                    $logMessage->addPHPErrorMessage($errorMessage, $errorFile, $errorNumber, $errorLine);
                    Log::message($logMessage);
                    break;
            }

            /* Don't execute PHP internal error handler */
            return true;
        });

        // register PHP fatal errors
        register_shutdown_function(function () {
            if (!defined('KOLDY_FATAL_ERROR_HANDLER')) {
                // to prevent possible recursion if you run into problems with logger
                define('KOLDY_FATAL_ERROR_HANDLER', true);

                $fatalError = error_get_last();

                if ($fatalError !== null && $fatalError['type'] == E_ERROR) {
                    $errorNumber = E_ERROR;
                    $errorMessage = $fatalError['message'];
                    $errorFile = $fatalError['file'];
                    $errorLine = $fatalError['line'];

                    $logMessage = new \Koldy\Log\Message('error');
                    $logMessage->addPHPErrorMessage($errorMessage, $errorFile, $errorNumber, $errorLine);

                    Log::error($logMessage);
                }
            }
        });

        // check the log for any enabled adapter
        $logConfigs = static::getConfig('application')->get('log', []);
        $logConfigsCount = count($logConfigs);
        $logEnabled = false;

        for ($i = 0; $i < $logConfigsCount && !$logEnabled; $i++) {
            if (isset($logConfigs[$i]['enabled']) && $logConfigs[$i]['enabled'] === true) {
                $logEnabled = true;
            }
        }

        if ($logEnabled) {
            // TODO: Don't init if we don't have to and let's do the "post" work after request
            Log::init();
        }
    }

    /**
     * Run the application with given URI. If URI is not set, then application
     * will try to detect it automatically.
     *
     * @param string $uri [optional]
     * @throws ConfigException
     * @throws Exception
     */
    public static function run(string $uri = null): void
    {
        static::init($uri);

        if (!KOLDY_CLI) {
            // this is normal HTTP request that came from Web Server, so we'll handle it

            $uri = $uri ?? $_SERVER['REQUEST_URI'] ?? null;
            if ($uri == null) {
                static::terminateWithError('Something went wrong, $uri is not set');
            }

            static::$uri = $uri;

            try {
                $route = static::route();
                $route->prepareHttp(static::$uri);

                /*
                 NOTE: CSRF will not be handled automatically by framework any more
                if (Csrf::isEnabled() && (!Csrf::hasTokenStored() || !Csrf::hasCookieToken())) {
                    Csrf::generate();
                }
                */

                $response = $route->exec();

                if ($response instanceof AbstractResponse) {
                    $response->flush();

                } else {
                    print $response;
                }

            } catch (Throwable $e) { // something threw up

                // otherwise, route should handle exception
                // because of this, you can customize almost everything
                static::$routing->handleException($e);

            }

        } else {

            // and this is case when you're dealing with CLI request
            // scripts are stored in /application/scripts, but before that, we need to determine which script is called

            global $argv;
            // $argv[0] - this should be "index.php", but we don't need this at all

            try {
                // so, if you run your script as "php cli.php backup", you'll have only two elements
                // in the future, we might handle different number of parameters, but until that, we won't

                // you can also call script in module using standard colon as separator
                // example: php cli.php user:backup   -> where "user" is module and "backup" is script name

                if (!isset($argv[1])) {
                    throw new CliException('Script name is not set in your script call. Please use notation: php index.php script-name');
                }

                $script = $argv[1]; // this should be the second parameter
                static::$cliName = $script;

                if (preg_match('/^([a-zA-Z0-9\_\-\:]+)$/', $script)) {
                    $scriptName = $script;
                    $module = null;

                    if (strpos($script, ':') !== false) {
                        // script name has colon - it means that the script needs to be looked for in modules
                        $tmp = explode(':', $script);
                        $cliScriptPath = static::getApplicationPath("modules/{$tmp[0]}/scripts/{$tmp[1]}.php");
                        $scriptName = $tmp[0] . ':' . $tmp[1];
                        $module = $tmp[0];

                        if (is_dir(static::getApplicationPath("modules/{$module}"))) {
                            static::registerModule($module);
                        }
                    } else {
                        if ($script == 'koldy') {
                            $script = static::$cliName = Cli::getParameterOnPosition(2);

                            if ($script === null) {
                                throw new CliException('Unable to run Koldy script; script name not passed after \'koldy\' part');
                            }

                            $cliScriptPath = __DIR__ . '/Cli/Scripts/' . $script . '.php';
                        } else {
                            $cliScriptPath = static::$scriptsPath . $script . '.php';
                        }
                    }

                    static::$cliScriptPath = $cliScriptPath;

                    if (!is_file($cliScriptPath)) {
                        throw new CliException("CLI script={$scriptName} on path={$cliScriptPath} does not exists");
                    } else {
                        Log::notice("Started CLI script: {$scriptName}");
                        require $cliScriptPath;
                        Log::notice("Done with CLI script: {$scriptName}");
                    }
                } else {
                    throw new CliException("CLI script={$script} contains invalid characters; please stick to english letters, numbers, dashes and underlines");
                }

                exit(0);

            } catch (Throwable $e) {
                if (!Log::isEnabledLogger('\Koldy\Log\Adapter\Out')) {
                    echo "{$e->getMessage()} in {$e->getFile()}:{$e->getLine()}\n\n{$e->getTraceAsString()}";
                }
                Log::critical($e);

                exit(1);
            }
        }

    }

}
