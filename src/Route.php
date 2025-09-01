<?php declare(strict_types=1);

namespace Koldy;

use Koldy\Route\{AbstractRoute, Exception as RouteException};

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
	 *
	 * @return string|null
	 * @throws Exception
	 */
	public static function getVar(string|int $whatVar): ?string
	{
		return Application::route()->getVar($whatVar);
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
		return $controller == Application::route()->getControllerUrl() && $action == Application::route()
				->getActionUrl();
	}

	/**
	 * Is this the matching module, controller and action?
	 *
	 * @param string $module
	 * @param string|null $controller
	 * @param string|null $action
	 *
	 * @return bool
	 * @throws Exception
	 */
	public static function isModule(string $module, string|null $controller = null, string|null $action = null): bool
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
	 * Generate the link to other site defined in sites.php, suitable for <a> tags. Generating links depends
	 * about the routing class you're using.
	 *
	 * @param string $site
	 * @param string|null $controller
	 * @param string|null $action
	 * @param array|null $params
	 *
	 * @return string
	 * @throws Exception
	 */
	public static function siteHref(
		string|null $site,
		string|null $controller = null,
		string|null $action = null,
		array|null $params = null
	): string {
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
	public static function site(string $site, string|null $uri = null): string
	{
		$otherSite = Application::getConfig('sites')->get($site);

		if ($otherSite === null) {
			throw new Exception("Unable to construct URL to site={$site}, site is not defined in configs/sites.php");
		}

		if ($uri === null || strlen($uri) === 0) {
			return $otherSite;
		} else {
			if ($uri[0] != '/') {
				$uri = '/' . $uri;
			}

			return $otherSite . $uri;
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
	 * Generate the link suitable for <a> tags. Generating links depends about the routing class you're using.
	 *
	 * @param string|null $controller
	 * @param string|null $action
	 * @param array|null $params
	 *
	 * @return string
	 * @throws Exception
	 */
	public static function href(
		string|null $controller = null,
		string|null $action = null,
		array|null $params = null
	): string {
		return Application::route()->href($controller, $action, $params);
	}

	/**
	 * Generate link to static asset on the same host where application is. This method is using link() method in
	 * routing class, so be careful because it might be overridden in your case.
	 *
	 * @param string $path
	 * @param string|null $server
	 *
	 * @return string
	 * @throws Config\Exception
	 * @throws Exception
	 * @throws RouteException
	 */
	public static function asset(string $path, string|null $server = null): string
	{
		$route = Application::route();
		return $route->asset($path, $server);
	}

}
