<?php declare(strict_types = 1);

namespace Koldy;

use Koldy\Config\Exception as ConfigException;
use Koldy\Mail\Adapter\AbstractMailAdapter;

/**
 * Send e-mail using config/mail.php.
 *
 * @example
 *  Mail::create()
 *    ->subject('Test mail')
 *    ->to('some@mail.com')
 *    ->from('your@mail.com')
 *    ->body('Your text')
 *    ->send();
 *
 */
class Mail
{

    /**
     * Array of initialized adapters
     *
     * @var array
     */
    private static $adapters = [];

    /**
     * Get mail config
     *
     * @return Config
     */
    public static function getConfig(): Config
    {
        return Application::getConfig('mail', true);
    }

    /**
     * Get the cache adapter
     *
     * @param string|null $adapter
     *
     * @return AbstractMailAdapter
     * @throws ConfigException
     */
    protected static function getAdapter(string $adapter = null): AbstractMailAdapter
    {
        $key = $adapter ?? static::getConfig()->getFirstKey();

        if (isset(static::$adapters[$key])) {
            return static::$adapters[$key];
        }

        $config = static::getConfig();
        $configArray = $config->get($key, []);

        if (($configArray['enabled'] ?? false) === true) {
            if (isset($configArray['module'])) {
                Application::registerModule($configArray['module']);
            }

            $className = $configArray['adapter_class'] ?? null;

            if ($className === null) {
                throw new ConfigException("Key 'adapter_class' is not set in mail config={$key}");
            }

            if (!class_exists($className, true)) {
                throw new ConfigException("Unknown mail class={$className} under key={$adapter}");
            }

            static::$adapters[$key] = new $className($configArray['options'] ?? []);
        }

        return static::$adapters[$key];
    }

    /**
     * This will create mail adapter object for you by the config you pass.
     * Otherwise, config.mail.php will be used. You should handle the case in
     * your web app when mail is not enabled so before creating the Mail
     * object, check it with isEnabled() method. If you're sure that mail will
     * always be enabled, then you don't need to check this.
     *
     * @param string|null $adapter
     *
     * @return Mail\Adapter\AbstractMailAdapter or false
     * @link http://koldy.net/docs/mail#create
     */
    public static function create(string $adapter = null): AbstractMailAdapter
    {
        return static::getAdapter($adapter);
    }

    /**
     * You can check if configure mail adapter is enabled or not.
     *
     * @param string $adapter [optional] will use default if not set
     *
     * @return boolean
     */
    public static function isEnabled(string $adapter = null): bool
    {
        return static::getAdapter($adapter) !== null;
    }

}
