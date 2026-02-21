<?php

namespace Koldy\Route\HttpRoute;

/**
 * @template T as array<string, mixed>
 */
abstract class HttpController
{

	/**
	 * @var T
	 */
	public array $context;

	public string|null $segment;

	/**
	 * Array of already executed code across http controllers
	 * @var array
	 */
	private static array $codeExecutions = [];

	public function __construct(array $data)
	{
		$this->context = array_key_exists('context', $data) ? $data['context'] : [];
		$this->segment = is_string($data['segment']) ? $data['segment'] : null;
	}

	/**
	 * Handler that helps you execute certain code only once. This is for advanced usage, when you create a structure
	 * of HTTP routes that might extend other abstract controllers. Common use case is to create abstract controller
	 * that validates access to the certain route. But since you can extend multiple controllers and depending on the
	 * depth of routes, it's possible that abstract controller could be executed multiple times. This method will
	 * ensure that you execute certain code only once in one request cycle.
	 *
	 * @param string $uniqueCodeName
	 * @param callable $callback
	 *
	 * @return void
	 */
	protected function once(string $uniqueCodeName, callable $callback): void
	{
		if (!array_key_exists($uniqueCodeName, self::$codeExecutions)) {
			self::$codeExecutions[$uniqueCodeName] = true;
			$callback();
		}
	}

}
