<?php declare(strict_types=1);

namespace Koldy;

/**
 * Class Autoloader - Provides ability to manually register Koldy framework autoload with non-default settings.
 *
 * To manually register Koldy framework, include manually this class and then call:
 * Koldy\Autoloader::register();
 *
 * Otherwise, if you're looking for internal autoloader that is automatically being used, then look for Koldy\Application::autoload()
 * @package Koldy
 */
class Autoloader
{

    /**
     * @var string
     */
    private static $directory = null;

    /**
     * @var string
     */
    private static $prefix = null;

    /**
     * @var int
     */
    private static $prefixLength = null;

    /**
     * @param string $baseDirectory Base directory where the source files are located.
     */
    public static function init(string $baseDirectory = null)
    {
        if (static::$directory === null || $baseDirectory !== null) {
	        static::$directory = $baseDirectory ?? __DIR__;
	        static::$prefix = __NAMESPACE__ . '\\';
	        static::$prefixLength = strlen(static::$prefix);
        }
    }

    /**
     * Registers the autoloader class with the PHP SPL autoloader.
     *
     * @param bool $prepend Prepend the autoloader on the stack instead of appending it.
     */
    public static function register(bool $prepend = false): void
    {
	    static::init();
        spl_autoload_register('\Koldy\Autoloader::autoload', true, $prepend);
    }

    /**
     * Unregisters the autoloader class with the PHP SPL autoloader. It's opposite from register function.
     */
    public static function unregister(): void
    {
        spl_autoload_unregister('\Koldy\Autoloader::autoload');
    }

    /**
     * Loads a class from a file using its fully qualified name.
     *
     * @param string $className Fully qualified name of a class.
     */
    public static function autoload(string $className): void
    {
        if (0 === strpos($className, static::$prefix)) {
            $parts = explode('\\', substr($className, static::$prefixLength));
            $filepath = static::$directory . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts) . '.php';

            if (is_file($filepath)) {
                require $filepath;
            }
        }
    }

}
