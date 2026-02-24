<?php declare(strict_types=1);

namespace Koldy\Route;

use InvalidArgumentException;
use Koldy\Application;
use Koldy\Exception;
use Koldy\Log;
use Koldy\Request;
use Koldy\Response\Exception\NotFoundException;
use Koldy\Response\Exception\ServerException;
use Koldy\Response\ResponseExceptionHandler;
use Koldy\Route\HttpRoute\HttpController;
use Koldy\Util;
use Koldy\Validator\ConfigException;
use Throwable;

/**
 * HttpRoute is a filesystem-based HTTP router that maps URI segments to namespaced PHP classes.
 *
 * Unlike the legacy DefaultRouter (which relied on PHP's include path), this router uses PHP's built-in
 * autoloader and PSR-4 class resolution. Each URI segment maps to a class within the configured root namespace,
 * and the final class handles the request via HTTP method-named methods (get(), post(), patch(), delete(), etc.).
 *
 * ## How routing works
 *
 * URI segments are processed left-to-right. Each segment is sanitized (slugified, then PascalCased) and resolved
 * to a class. The router walks the namespace tree, instantiating each controller along the way, passing context
 * forward. The last controller in the chain receives the HTTP method call.
 *
 * Example: `GET /companies/splendido-solutions/invoices`
 * 1. `companies`            → no `App\Http\__`, falls back to `App\Http\Companies` (static match)
 * 2. `splendido-solutions`  → `App\Http\Companies\__` (dynamic match takes precedence)
 * 3. `invoices`             → `App\Http\Companies\__\Invoices` → `get()` is called (last segment)
 *
 * ## Dynamic vs static matching
 *
 * For each segment, the router first tries a **dynamic match** — a class named `__` (double underscore) in the
 * current namespace. The `__` class acts as a wildcard/catch-all and receives the raw segment value via the
 * `$this->segment` property, which is useful for capturing dynamic parameters like UUIDs or slugs.
 * If no `__` class exists, it falls back to a **static match** — a class whose PascalCased name matches the segment.
 * This means that if both a `__` class and a static class exist at the same level, the `__` class always wins.
 *
 * ## Context propagation
 *
 * Each controller in the chain receives a `$context` array and a `$segment` string via its constructor (as part
 * of the `$data` array passed to {@see HttpController::__construct()}). Controllers can enrich the context
 * (e.g. load a model by UUID) and the next controller in the chain will receive the updated context. This allows
 * deep URI structures to accumulate state without global variables.
 *
 * ## Sanitization
 *
 * URI segments are converted to class names via {@see sanitize()}: the segment is slugified ({@see Util::slug()}),
 * double dashes collapsed, dashes replaced with spaces, ucwords applied, then spaces removed.
 * Example: `bank-accounts` → `BankAccounts`, `my-awesome-page` → `MyAwesomePage`
 *
 * ## Exception handling
 *
 * If an `ExceptionHandler` class exists in the configured root namespace (e.g. `App\Http\ExceptionHandler`),
 * it will be used to handle exceptions. Otherwise, the framework's default {@see ResponseExceptionHandler} is used.
 * The custom handler must have an `exec()` method.
 *
 * ## Configuration
 *
 * Set `routing_class` to `Koldy\Route\HttpRoute` and configure `routing_options`:
 *
 * ```php
 * 'routing_options' => [
 *   'namespace' => 'App\\Http\\',   // required — root namespace for all HTTP handler classes
 *   'debugFailure' => true,          // optional (default: false) — troubleshoot why route resolution fails
 *   'debugSuccess' => true           // optional (default: false) — troubleshoot why route resolution succeeds
 * ]
 * ```
 *
 * If namespaced files are outside the standard application library path, add the path to composer's
 * `autoload` section so classes can be autoloaded.
 *
 * ## Trailing slash behavior
 *
 * GET/HEAD requests with a trailing slash are 301-redirected to the same URI without the trailing slash
 * to avoid duplicate content.
 *
 * @template TContext of array
 *
 * @extends AbstractRoute<array{namespace: string, debugFailure?: bool, debugSuccess?: bool}>
 */
class HttpRoute extends AbstractRoute
{

	/**
	 * The normalized URI path (double slashes collapsed, trimmed, trailing slash removed), without query string
	 */
	protected string|null $uri = null;

	/**
	 * The array of URI segments, split by "/". The leading slash is stripped before splitting, so there is no
	 * leading empty string. For example, `/companies/splendido-solutions/invoice` results in:
	 * `['companies', 'splendido-solutions', 'invoice']`
	 *
	 * Index 0 is the first segment, index 1 is the second, etc.
	 */
	protected array|null $uriParts = null;

	/**
	 * The HTTP method, lowercased
	 */
	protected string|null $method = null;

	/**
	 * The namespace where the HTTP (root) controllers are located
	 */
	protected string|null $namespace = null;

	/**
	 * Set to true in config to enable debug mode. Useful in development mode
	 */
	protected bool $debugFailure = false;

	/**
	 * Set to true in config to enable debug mode. Useful in development mode
	 */
	protected bool $debugSuccess = false;

	/**
	 * The array of context data that will be passed to the controller
	 *
	 * @var TContext
	 */
	protected array $context = [];


	/**
	 * Start the HTTP router. Start should be called only once at the beginning of the request.
	 *
	 * @param string $uri
	 *
	 * @return mixed
	 * @throws InvalidArgumentException
	 * @throws Exception
	 */
	public function start(string $uri): mixed
	{
		// the incoming $uri MUST start with "/"

		if (!str_starts_with($uri, '/')) {
			throw new InvalidArgumentException('URI must start with slash');
		}

		$uri = rawurldecode($uri);
		$url = parse_url($uri);

		if (!is_array($url) || !array_key_exists('path', $url)) {
			throw new InvalidArgumentException('Invalid URI given');
		}

		$this->uri = $url['path'];
		$query = $url['query'] ?? '';

		while (str_contains($this->uri, '//')) {
			// remove double slashes
			$this->uri = str_replace('//', '/', $this->uri);
		}

		$this->uri = trim($this->uri);

		$method = strtolower(Request::method());
		$isGet = $method === 'get' || $method === 'head';
		if ($isGet && str_ends_with($this->uri, '/') && strlen($this->uri) > 1) {
			// cut the trailing slash
			$this->uri = substr($this->uri, 0, -1);

			$newUri = $this->uri;
			if ($query !== '') {
				$newUri .= "?{$query}";
			}

			// redirect to the same page, but without trailing slash - we do this because we don't want to have duplicate content
			// just because of trailing slash
			header("Location: {$newUri}", true, 301);
			exit(0);
		}

		if (!isset($this->config['namespace'])) {
			Application::terminateWithError('The application routing_options.namespace has to be string');
		}

		if (isset($this->config['debugFailure']) && $this->config['debugFailure'] === true) {
			$this->debugFailure = true;
		}

		if (isset($this->config['debugSuccess']) && $this->config['debugSuccess'] === true) {
			$this->debugSuccess = true;
		}

		$this->namespace = $this->config['namespace']; // save the "root" namespace
		$this->uriParts = explode('/', substr($this->uri, 1));
		$this->method = strtolower(Request::method());

		try {
			return $this->exec();
		} catch (Throwable $e) {
			$this->handleException($e);
			return null;
		}
	}

	/**
	 * @return mixed
	 * @throws NotFoundException
	 * @throws ServerException
	 */
	private function exec(): mixed
	{
		// let's iterate every segment
		$classPath = $this->namespace;
		$count = count($this->uriParts);

		for ($i = 0; $i < $count; $i++) {
			$segment = $this->uriParts[$i];
			$name = $this->sanitize($segment);
			$isLast = $i === $count - 1;

			// rule 1: dynamic match (__) — always takes precedence if available
			// rule 2: if no dynamic, try static match (class whose name matches the segment)

			$dynamicClassName = "{$classPath}__";
			$staticClassName = "{$classPath}{$name}";

			$instance = null;

			// rule 1: dynamic match
			if (class_exists($dynamicClassName) && !is_subclass_of($dynamicClassName, HttpController::class)) {
				throw new ServerException("Class {$dynamicClassName} exists but does not extend HttpController");
			}

			if (class_exists($dynamicClassName)) {
				try {
					$instance = new $dynamicClassName($this->constructConstructor($segment));
					$classPath .= '__\\';

					if ($this->debugSuccess) {
						Log::debug("HTTP: via  {$dynamicClassName}->__construct()");
					}
				} catch (\Error $e) {
					if (str_contains($e->getMessage(), 'Cannot instantiate')) {
						if ($this->debugFailure) {
							Log::debug("HTTP: skip {$dynamicClassName}, cannot instantiate: {$e->getMessage()}");
						}
						$instance = null;
					} else {
						throw $e;
					}
				}
			}

			// rule 2: static match (if dynamic didn't work)
			if ($instance === null && class_exists($staticClassName) && !is_subclass_of($staticClassName, HttpController::class)) {
				throw new ServerException("Class {$staticClassName} exists but does not extend HttpController");
			}

			if ($instance === null && class_exists($staticClassName)) {
				try {
					$instance = new $staticClassName($this->constructConstructor($segment));
					$classPath .= $name . '\\';

					if ($this->debugSuccess) {
						Log::debug("HTTP: via  {$staticClassName}->__construct()");
					}
				} catch (\Error $e) {
					if (str_contains($e->getMessage(), 'Cannot instantiate')) {
						if ($this->debugFailure) {
							Log::debug("HTTP: skip {$staticClassName}, cannot instantiate: {$e->getMessage()}");
						}
						$instance = null;
					} else {
						throw $e;
					}
				}
			}

			if ($instance !== null) {
				$this->context = $instance->context;

				if ($isLast) {
					if (method_exists($instance, $this->method)) {
						if ($this->debugSuccess) {
							$cls = get_class($instance);
							Log::debug("HTTP: exec {$cls}->{$this->method}()");
						}

						return $instance->{$this->method}();
					} else {
						if ($this->debugFailure) {
							$cls = get_class($instance);
							Log::debug("HTTP: fail {$cls}->{$this->method}() not found");
						}

						throw new NotFoundException('Endpoint not found');
					}
				}
			} else {
				// neither found or neither could be instantiated — advance class path and continue
				if ($this->debugFailure) {
					Log::debug("HTTP: miss {$staticClassName}");
				}

				$classPath .= $name . '\\';
			}
		}

		if ($this->debugFailure) {
			Log::debug("HTTP: no response content found for {$this->uri}");
		}

		throw new NotFoundException('Endpoint not found');
	}

	private function constructConstructor(string|null $segment): array
	{
		return [
			'context' => $this->context,
			'segment' => $segment
		];
	}

	private function sanitize(string $segment): string
	{
		$segment = Util::slug($segment);

		while (str_contains($segment, '--')) {
			$segment = str_replace('--', '-', $segment);
		}

		$segment = str_replace('-', ' ', $segment);
		$segment = ucwords($segment);
		return str_replace(' ', '', $segment);
	}

	/**
	 * @throws ConfigException
	 * @throws Exception
	 * @throws \Koldy\Json\Exception
	 * @throws \Koldy\Validator\Exception
	 */
	public function handleException(Throwable $e): void
	{
		$exceptionHandlerClassName = "{$this->namespace}ExceptionHandler";
		$thrownClassName = get_class($e);

		if (class_exists($exceptionHandlerClassName) && method_exists($exceptionHandlerClassName, 'exec')) {
			if ($this->debugFailure) {
				Log::debug("HTTP: exception [{$exceptionHandlerClassName}], caught [{$thrownClassName}]");
			}
			$exceptionHandler = new $exceptionHandlerClassName($e);
			$exceptionHandler->exec();
		} else {
			if ($this->debugFailure) {
				$cls = ResponseExceptionHandler::class;
				Log::debug("HTTP: default exception [{$cls}], caught [{$thrownClassName}]");
			}
			$exceptionHandler = new ResponseExceptionHandler($e);
			$exceptionHandler->exec();
		}
	}

}
