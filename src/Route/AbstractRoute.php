<?php declare(strict_types=1);

namespace Koldy\Route;

use Throwable;

/**
 * To create your own routing system, you must extend this class and then,
 * in config/application.php under 'routing_class' set the name of your own class.
 *
 * Routing class must do the following:
 * - parse request URI and determine whats controller, action and parameters
 * - generate proper URLs with controller, action and parameters
 * - handle the error pages
 * - handle the exceptions
 *
 * @template TConfig of array
 */
abstract class AbstractRoute
{

	/**
	 * The route_config from config/application.php
	 *
	 * @var TConfig
	 */
	protected array $config;

	/**
	 * Construct the object
	 *
	 * @param array $config [optional]
	 *
	 * @example parameter might be "/user/login"
	 */
	public function __construct(array $config = [])
	{
		$this->config = $config;
	}

	/**
	 * If your app throws any kind of exception, it will end up here, so, handle it!
	 *
	 * @param Throwable $e
	 */
	abstract public function handleException(Throwable $e): void;

	/**
	 * Start the HTTP router. Start should be called only once at the beginning of the request.
	 *
	 * @param string $uri
	 *
	 * @return mixed
	 */
	abstract public function start(string $uri): mixed;

}
