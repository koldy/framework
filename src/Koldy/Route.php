<?php declare(strict_types=1);

namespace Koldy;

use Koldy\Route\{
  AbstractRoute, Exception as RouteException
};

/**
 * This is another utility class that know how to handle URL. While developing
 * your site, you'll probably need to generate URL and detect if you're
 * currently on some given URL. This class provides all of it.
 *
 * This class relies on your route instance so you'll probably need to check
 * the docs of your routes to understand the methods below.
 */
class Route
{

    /**
     * Get the initialized routing class
     *
     * @return AbstractRoute
     * @throws Exception
     */
    public static function getRoute(): AbstractRoute
    {
        return Application::route();
    }

    /**
     * Get the variable from request. This depends about the route you're using.
     *
     * @param string|int $whatVar
     * @param mixed $default
     *
     * @return string
     * @throws Exception
     */
    public static function getVar($whatVar, $default = null)
    {
        return Application::route()->getVar($whatVar, $default);
    }

    /**
     * Get the controller name in the exact format as its being used in URL
     *
     * @return string
     * @throws Exception
     */
    public static function controller(): string
    {
        return Application::route()->getControllerUrl();
    }

    /**
     * Is given controller the current working controller?
     *
     * @param string $controller the url format (e.g. "index"), not the class name such as "IndexController"
     *
     * @return bool
     * @throws Exception
     */
    public static function isController(string $controller): bool
    {
        return $controller == Application::route()->getControllerUrl();
    }

    /**
     * Get the current action in the exact format as it is being used in URL
     *
     * @return string
     * @throws Exception
     */
    public static function action(): string
    {
        return Application::route()->getActionUrl();
    }

    /**
     * Is given action the current working action?
     *
     * @param string $action the url format (e.g. "index"), not the method name such as "indexAction"
     *
     * @return bool
     * @throws Exception
     */
    public static function isAction(string $action): bool
    {
        return $action == Application::route()->getActionUrl();
    }

    /**
     * Are given controller and action current working controller and action?
     *
     * @param string $controller in the url format
     * @param string $action in the url format
     *
     * @return bool
     * @throws Exception
     */
    public static function is(string $controller, string $action): bool
    {
        return $controller == Application::route()->getControllerUrl() && $action == Application::route()->getActionUrl();
    }

    /**
     * Is this the matching module, controller and action?
     *
     * @param string $module
     * @param string $controller
     * @param string $action
     *
     * @return bool
     * @throws Exception
     */
    public static function isModule(string $module, string $controller = null, string $action = null): bool
    {
        $route = Application::route();
        if ($module === $route->getModuleUrl()) {
            if ($controller === null) {
                return true;
            } else {
                if ($controller === $route->getControllerUrl()) {
                    // now we have matched module and controller
                    if ($action === null) {
                        return true;
                    } else {
                        return ($action === $route->getActionUrl());
                    }
                } else {
                    return false;
                }
            }
        } else {
            return false;
        }
    }

    /**
     * Generate the link suitable for <a> tags. Generating links depends about the routing class you're using.
     *
     * @param string $controller
     * @param string $action
     * @param array $params
     *
     * @return string
     * @throws Exception
     */
    public static function href($controller = null, string $action = null, array $params = null): string
    {
        return Application::route()->href($controller, $action, $params);
    }

    /**
     * Generate the link to other site defined in sites.php, suitable for <a> tags. Generating links depends
     * about the routing class you're using.
     *
     * @param string $site
     * @param string $controller
     * @param string $action
     * @param array $params
     *
     * @return string
     * @throws Exception
     */
    public static function siteHref(string $site, string $controller = null, string $action = null, array $params = null): string
    {
        return Application::route()->siteHref($site, $controller, $action, $params);
    }

    /**
     * Unlike siteHref which generates URI the same way as href(), site() just accepts anything for URI and appends it
     *
     * @param string $site
     * @param string|null $uri - URI with leading "/"
     *
     * @return string
     * @throws Exception
     */
    public static function site(string $site, string $uri = null): string
    {
        $otherSite = Application::getConfig('sites')->get($site);

        if ($otherSite === null) {
            throw new Exception("Unable to construct URL to site={$site}, site is not defined in configs/sites.php");
        }

        if ($uri === null) {
            return $otherSite;
        } else {
            return $otherSite . '/' . $uri;
        }
    }

    /**
     * Generate the link to home page
     *
     * @return string
     * @throws Exception
     */
    public static function home(): string
    {
        return static::href();
    }

    /**
     * Generate link to static asset on the same host where application is. This method is using link() method in
     * routing class, so be careful because it might be overridden in your case.
     *
     * @param string $path
     * @param string $server
     *
     * @return string
     * @throws Exception
     */
    public static function asset(string $path, string $server = null): string
    {
        $route = Application::route();

        if (!($route instanceof AbstractRoute)) {
            throw new RouteException('Invalid route; expected instance of \Koldy\Route\AbstractRoute, got ' . gettype($route));
        }

        return $route->asset($path, $server);
    }

}
