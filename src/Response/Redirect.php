<?php declare(strict_types=1);

namespace Koldy\Response;

use Koldy\Application;
use Koldy\Exception;
use Koldy\Route;

/**
 * Response Redirection client to another Location.
 */
class Redirect extends AbstractResponse
{

	/**
	 * Permanent redirect (301) to the given URL
	 *
	 * @param string $where
	 *
	 * @return static
	 */
	public static function permanent(string $where): Redirect
	{
		// @phpstan-ignore-next-line due to @phpstan-consistent-constructor
		$self = new static();
		$self->statusCode(301)
			->setHeader('Location', $where)
			->setHeader('Connection', 'close')
			->setHeader('Content-Length', 0);

		return $self;
	}

	/**
	 * Alias to temporary() method (302)
	 *
	 * @param string $where
	 *
	 * @return static
	 * @example http://www.google.com
	 */
	public static function to(string $where): Redirect
	{
		return static::temporary($where);
	}

	/**
	 * Temporary redirect (302) to the given URL
	 *
	 * @param string $where
	 *
	 * @return static
	 */
	public static function temporary(string $where): Redirect
	{
		// @phpstan-ignore-next-line due to @phpstan-consistent-constructor
		$self = new static();
		$self->statusCode(302)
			->setHeader('Location', $where)
			->setHeader('Connection', 'close')
			->setHeader('Content-Length', 0);

		return $self;
	}

	/**
	 * Redirect client (302) to home page
	 *
	 * @return static
	 *
	 * @deprecated Use Redirect::temporary('/') or Redirect::permanent('/') instead
	 */
	public static function home(): Redirect
	{
		return static::temporary('/');
	}

	/**
	 * Redirect client (302) to the URL generated with Route::href method
	 *
	 * @param string|null $controller
	 * @param string|null $action
	 * @param array|null $params
	 *
	 * @return static
	 * @throws Exception
	 * @deprecated Use your own router to generate the URL
	 */
	public static function href(
		string|null $controller = null,
		string|null $action = null,
		array|null $params = null
	): Redirect {
		$route = Application::route();
		/** @var Route\DefaultRoute $route */
		return static::temporary($route->href($controller, $action, $params));
	}

	/**
	 * Redirect client the the given link under the same domain.
	 *
	 * @param string $path
	 * @param string|null $assetSite
	 *
	 * @return static
	 * @throws \Koldy\Config\Exception
	 * @throws Exception
	 * @deprecated Use Application::getConfig('application')->get('assets') to get the asset URL
	 */
	public static function link(string $path, string|null $assetSite = null): Redirect
	{
		$route = Application::route();
		/** @var Route\DefaultRoute $route */
		return self::temporary($route->asset($path, $assetSite));
	}

	/**
	 * Redirect client to asset URL (defined by key in mandatory config under assets)
	 *
	 * @param string $path
	 * @param string|null $assetKey
	 *
	 * @return static
	 * @throws Route\Exception
	 * @throws \Koldy\Config\Exception
	 * @throws Exception
	 *
	 * @deprecated Use Application::getConfig('application')->get('assets') to get the asset URL
	 */
	public static function asset(string $path, string|null $assetKey = null): Redirect
	{
		return self::temporary(Route::asset($path, $assetKey));
	}

	public function getOutput(): mixed
	{
		// the Redirect response does not have any output
		return null;
	}

	/**
	 * Run Redirect
	 */
	public function flush(): void
	{
		$this->prepareFlush();
		$this->runBeforeFlush();

		$this->setHeader('Content-Length', 0);
		$this->flushHeaders();
		flush();

		$this->runAfterFlush();
	}

}
