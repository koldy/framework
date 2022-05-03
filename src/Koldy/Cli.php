<?php declare(strict_types=1);

namespace Koldy;

use Koldy\Cli\Exception as CliException;

/**
 * This is helper class when you're running your scripts in CLI env. By using
 * this class, you can easily catch some script parameters passed to the
 * script call.
 */
class Cli
{

    /**
     * The global $argv variable
     *
     * @var array|null
     */
    protected static array | null $argv = null;

    /**
     * Parsed parameters from script arguments
     *
     * @var array|null
     */
    protected static array | null $parameters = null;

    /**
     * Get the $argv global variable in CLI env
     *
     * @throws CliException
     * @return array
     */
    public static function getArgv(): array
    {
        if (PHP_SAPI != 'cli') {
            throw new CliException('Can not get $argv because script is not called from CLI');
        }

        if (static::$argv !== null) {
            return static::$argv;
        }

        global $argv;

        if (!isset($argv) || !is_array($argv)) {
            throw new CliException('Can not access the $argv variable. You\'re probably not in the CLI env.');
        }

        static::$argv = $argv;
        return static::$argv;
    }

    /**
     * Parse the script arguments into parameters ready for later use
     * @throws CliException
     */
    protected static function parseArgvIntoParameters(): void
    {
        if (static::$parameters === null) {
            static::$parameters = [];
            $argv = static::getArgv();
            array_shift($argv); // remove the script name, it's not needed
            $sizeof = count($argv);

            for ($i = 0; $i < $sizeof; $i++) {
                $p = $argv[$i];

                if (str_starts_with($p, '--')) {
                    $tmp = explode('=', $p);
                    static::$parameters[substr($tmp[0], 2)] = $tmp[1] ?? null;

                } else if (str_starts_with($p, '-') && isset($argv[$i + 1]) && !str_starts_with($argv[$i + 1], '-')) {
                    static::$parameters[substr($p, 1)] = $argv[$i + 1] ?? null;

                } else if (str_starts_with($p, '-') && preg_match('/^[a-zA-Z]$/', substr($p, 1, 1))) {
                    static::$parameters[substr($p, 1, 1)] = null;

                }

            }
        }
    }

    /**
     * Is there given parameter name in the script arguments
     *
     * @param string $parameter
     *
     * @return boolean
     * @throws CliException
     * @example if called index.php -x --title-name="the title" --version=5,
     * then you can use hasParameterName('title-name') or hasParameterName('version')
     */
    public static function hasParameter(string $parameter): bool
    {
        static::parseArgvIntoParameters();
        return array_key_exists($parameter, static::$parameters);
    }

	/**
	 * Get the parameter's value
	 *
	 * @param string $name
	 *
	 * @return string|null or null if parameter doesn't exist
	 * @throws CliException
	 * @example if called index.php -x --title-name="the title" --version=5,
	 * then you can use getParameterValue('title-name') would return "the title" and getParameterValue('version') would return "5"
	 */
    public static function getParameter(string $name): ?string
    {
        static::parseArgvIntoParameters();
        return static::$parameters[$name] ?? null;
    }

	/**
	 * Get the parameter from any position
	 *
	 * @param int $index starting from zero
	 *
	 * @return string|null or null if parameter doesn't exists on that place
	 *
	 * @throws CliException
	 * @example if called "index.php 123 -p 2 --version=1.0.1 -h localhost"
	 * and you call getParameterOnPosition(4), you'll get "--version=1.0.1"
	 */
    public static function getParameterOnPosition(int $index): ?string
    {
        static::parseArgvIntoParameters();
        return static::$argv[$index] ?? null;
    }

    /**
     * Is there any parameter set on given position?
     *
     * @param int $index
     *
     * @return boolean
     * @throws CliException
     */
    public static function hasParameterOnPosition(int $index): bool
    {
        static::parseArgvIntoParameters();
        return array_key_exists($index, static::$argv);
    }

    /**
     * Get all parsed parameters
     *
     * @return array
     * @throws CliException
     */
    public static function getParameters(): array
    {
        static::parseArgvIntoParameters();
        return static::$parameters;
    }

}
