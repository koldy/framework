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
     * @var string|null
     */
    protected static string | null $directory = null;

    /**
     * @var string|null
     */
	protected static string | null $prefix = null;

    /**
     * @var int|null
     */
	protected static int | null $prefixLength = null;

	/**
	 * @param string|null $baseDirectory Base directory where the source files are located.
	 */
    public static function init(string|null $baseDirectory = null): void
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
        if (str_starts_with($className, static::$prefix)) {
            $parts = explode('\\', substr($className, static::$prefixLength));
            $filepath = static::$directory . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts) . '.php';

            if (is_file($filepath)) {
                require $filepath;
            }
        }
    }

}
