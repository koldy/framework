<?php declare(strict_types=1);

namespace Koldy;

use Koldy\Config\Exception as ConfigException;
use Koldy\Mail\Adapter\AbstractMailAdapter;
use Koldy\Mail\Adapter\Simulate;

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
     * First config key in configs/mail.php
     *
     * @var string|null
     */
    protected static string|null $firstKey = null;

    /**
     * Get mail config
     *
     * @return Config
     * @throws Exception
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
     * @throws Exception
     */
    protected static function getAdapter(?string $adapter = null): AbstractMailAdapter
    {
        $config = static::getConfig();

        if (static::$firstKey === null) {
            static::$firstKey = $config->getFirstKey();
        }

        $key = $adapter ?? static::$firstKey;
        $configArray = $config->get($key) ?? [];

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

            return new $className($configArray['options'] ?? []);
        }

        return new Simulate($configArray['options'] ?? []);
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
     * @throws ConfigException
     * @throws Exception
     * @link http://koldy.net/docs/mail#create
     */
    public static function create(string $adapter = null): AbstractMailAdapter
    {
        return static::getAdapter($adapter);
    }

    /**
     * You can check if configure mail adapter is enabled or not.
     *
     * @param string $adapter
     *
     * @return boolean
     * @throws ConfigException
     * @throws Exception
     */
    public static function isEnabled(string $adapter): bool
    {
        $config = static::getConfig();

        if (!$config->has($adapter)) {
            return false;
        }

        $enabled = $config->getArrayItem($adapter, 'enabled');
        if ($enabled === null) {
            return false;
        }

        return (bool)$enabled;
    }

}
