<?php declare(strict_types=1);

namespace Koldy\Route;

use InvalidArgumentException;
use Koldy\Application;
use Koldy\Exception;
use Koldy\Log;
use Koldy\Request;
use Koldy\Response\AbstractResponse;
use Koldy\Response\Exception\MethodNotAllowedException;
use Koldy\Response\Exception\NotFoundException;
use Koldy\Response\Exception\ServerException;
use Koldy\Response\Redirect;
use Koldy\Response\ResponseExceptionHandler;
use Koldy\Route\HttpRoute\HttpController;
use Koldy\Util;
use Koldy\Validator\ConfigException;
use ReflectionClass;
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
 * ## Root route
 *
 * The root route (`/`) is handled by a class whose fully qualified name matches the configured namespace
 * without the trailing backslash. For example, if `namespace` is `App\Http\`, the root handler is the
 * class `App\Http` (i.e. class `Http` in namespace `App`). This class must extend {@see HttpController}.
 *
 * ```php
 * // App/Http.php
 * namespace App;
 * class Http extends HttpController {
 *     public function get(): mixed { return /* homepage * /; }
 * }
 * ```
 *
 * ## Dynamic vs static matching
 *
 * For each segment, the router first tries a **dynamic match** — a class named `__` (double underscore) in the
 * current namespace. The `__` class acts as a wildcard/catch-all and receives the raw segment value via the
 * `$this->segment` property, which is useful for capturing dynamic parameters like UUIDs or slugs.
 * If no `__` class exists, it falls back to a **static match** — a class whose PascalCased name matches the segment.
 * This means that if both a `__` class and a static class exist at the same level, the `__` class always wins.
 *
 * Classes that exist but are not instantiable (abstract, private constructor, etc.) are skipped via
 * {@see ReflectionClass::isInstantiable()} rather than caught from a thrown `\Error`.
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
 * ## HEAD requests
 *
 * HEAD requests are dispatched to the controller's `head()` method if defined. Otherwise, if `get()` exists,
 * it is invoked and its rendered response is sent headers-only — the body is captured by an output buffer
 * (with a discard callback) so streaming responses don't accumulate in memory. This matches HTTP semantics:
 * HEAD must return the same headers as GET with no body. If neither `head()` nor `get()` is defined, the router
 * returns 405 (see "404 vs 405" below).
 *
 * ## 404 vs 405
 *
 * The router distinguishes between "no controller for this URI" (404 — {@see NotFoundException}) and
 * "controller resolved but the requested HTTP method isn't implemented" (405 — {@see MethodNotAllowedException}).
 * The 405 exception carries the list of implemented HTTP verbs via {@see MethodNotAllowedException::getAllowedMethods()};
 * the framework's default {@see ResponseExceptionHandler} reads that list and sets the `Allow:` response header.
 * OPTIONS is treated like any other verb — if `options()` is defined on the controller it is called, otherwise 405.
 * The router does **not** auto-respond to OPTIONS (CORS preflight is expected to be handled upstream, e.g. at nginx).
 *
 * ## Encoded slash inside segments
 *
 * Segments are extracted from the still-encoded URI path; only then is each segment individually decoded with
 * {@see rawurldecode()}. This means an encoded slash (`%2F`) inside a single segment decodes to a literal `/`
 * **within that segment** rather than creating a new segment boundary. For example, `/files/foo%2Fbar/meta`
 * yields three segments: `files`, `foo/bar`, `meta` — and the literal `/` is passed verbatim to the resolving
 * controller via `$this->segment`.
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
 * GET/HEAD requests with a trailing slash receive a 301 redirect (as a {@see Redirect} response) to the same URI
 * without the trailing slash, to avoid duplicate content. The router returns the redirect through the normal
 * response pipeline — it does not terminate the process.
 *
 * @template TContext of array
 *
 * @extends AbstractRoute<array{namespace: string, debugFailure?: bool, debugSuccess?: bool}>
 */
class HttpRoute extends AbstractRoute
{

	/**
	 * HTTP verbs scanned via reflection when building the {@see MethodNotAllowedException} allowed-methods list.
	 */
	private const HTTP_VERBS = ['get', 'post', 'put', 'patch', 'delete', 'head', 'options'];

	/**
	 * The normalized URI path (double slashes collapsed, trimmed, trailing slash removed), without query string
	 */
	protected string|null $uri = null;

	/**
	 * The array of URI segments, split by "/". The leading slash is stripped before splitting, so there is no
	 * leading empty string. For example, `/companies/splendido-solutions/invoice` results in:
	 * `['companies', 'splendido-solutions', 'invoice']`
	 *
	 * Each segment is `rawurldecode()`'d individually, so `%2F` becomes a literal `/` inside a single segment
	 * rather than creating a phantom split.
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

		// parse_url operates on the still-encoded URI — it does not decode percent-encoded characters in the path.
		// This is required so that an encoded slash (%2F) inside a segment doesn't split into two segments.
		$url = parse_url($uri);

		if (!is_array($url) || !array_key_exists('path', $url)) {
			throw new InvalidArgumentException('Invalid URI given');
		}

		// collapse runs of "/" in one pass (no loop required)
		$path = preg_replace('#/+#', '/', $url['path']);
		$path = trim($path);
		$query = $url['query'] ?? '';

		$method = strtolower(Request::method());
		$isGet = $method === 'get' || $method === 'head';
		if ($isGet && str_ends_with($path, '/') && strlen($path) > 1) {
			// cut the trailing slash and redirect — we do this so we don't have duplicate content
			// just because of trailing slash. Returning a Redirect lets the normal response lifecycle
			// run (afterAnyResponse callbacks, session close, log flushers) instead of calling exit().
			$path = substr($path, 0, -1);

			$newUri = $path;
			if ($query !== '') {
				$newUri .= "?{$query}";
			}

			return Redirect::permanent($newUri);
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
		$this->uri = $path;

		// Split the still-encoded path, then decode each segment individually so %2F stays inside its segment.
		$rawSegments = explode('/', substr($path, 1));
		$this->uriParts = array_map('rawurldecode', $rawSegments);
		$this->method = $method;

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
	 * @throws MethodNotAllowedException
	 * @throws ServerException
	 */
	private function exec(): mixed
	{
		// Handle root route "/" — uriParts is [''] in this case
		if (count($this->uriParts) === 1 && $this->uriParts[0] === '') {
			return $this->execRoot();
		}

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

			$dynamicExists = class_exists($dynamicClassName);
			$staticExists = class_exists($staticClassName);

			if ($dynamicExists && !is_subclass_of($dynamicClassName, HttpController::class)) {
				throw new ServerException("Class {$dynamicClassName} exists but does not extend HttpController");
			}

			if (!$dynamicExists && $staticExists && !is_subclass_of($staticClassName, HttpController::class)) {
				throw new ServerException("Class {$staticClassName} exists but does not extend HttpController");
			}

			$instance = null;

			// rule 1: dynamic match
			if ($dynamicExists) {
				$instance = $this->tryInstantiate($dynamicClassName, $segment);
				if ($instance !== null) {
					$classPath .= '__\\';

					if ($this->debugSuccess) {
						Log::debug("HTTP: via  {$dynamicClassName}->__construct()");
					}
				}
			}

			// rule 2: static match (if dynamic didn't work)
			if ($instance === null && $staticExists) {
				$instance = $this->tryInstantiate($staticClassName, $segment);
				if ($instance !== null) {
					$classPath .= $name . '\\';

					if ($this->debugSuccess) {
						Log::debug("HTTP: via  {$staticClassName}->__construct()");
					}
				}
			}

			if ($instance !== null) {
				$this->context = $instance->context;

				if ($isLast) {
					return $this->dispatch($instance);
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

	/**
	 * Handle the root route "/". The root handler class is the namespace itself without the trailing backslash.
	 * For example, if the namespace is "App\Http\", the root handler class is "App\Http".
	 *
	 * @return mixed
	 * @throws NotFoundException
	 * @throws MethodNotAllowedException
	 * @throws ServerException
	 */
	private function execRoot(): mixed
	{
		$rootClassName = rtrim($this->namespace, '\\');

		if (!class_exists($rootClassName)) {
			if ($this->debugFailure) {
				Log::debug("HTTP: no root handler class {$rootClassName} found for /");
			}

			throw new NotFoundException('Endpoint not found');
		}

		if (!is_subclass_of($rootClassName, HttpController::class)) {
			throw new ServerException("Class {$rootClassName} exists but does not extend HttpController");
		}

		$instance = $this->tryInstantiate($rootClassName, null);
		if ($instance === null) {
			// root class isn't instantiable (abstract, etc.) — fall through to 404 since no controller
			// is actually resolved; 405 wouldn't be correct because the resource itself doesn't exist.
			throw new NotFoundException('Endpoint not found');
		}

		if ($this->debugSuccess) {
			Log::debug("HTTP: root {$rootClassName}->__construct()");
		}

		return $this->dispatch($instance);
	}

	/**
	 * Build and dispatch the HTTP method call on the resolved controller. Encapsulates the precedence:
	 * explicit method → HEAD-falls-back-to-GET → `__call` → 405.
	 *
	 * @param HttpController $instance
	 *
	 * @return mixed
	 * @throws MethodNotAllowedException
	 */
	private function dispatch(HttpController $instance): mixed
	{
		$cls = get_class($instance);

		// Explicit method on controller wins. This also covers OPTIONS: if options() is defined we call it;
		// the router does not invent OPTIONS responses on its own.
		if (method_exists($instance, $this->method)) {
			if ($this->debugSuccess) {
				Log::debug("HTTP: exec {$cls}->{$this->method}()");
			}

			return $instance->{$this->method}();
		}

		// HEAD fallback to GET: per HTTP semantics, HEAD must return the same headers as GET with no body.
		// If the controller didn't explicitly define head(), invoke get() and strip the body.
		if ($this->method === 'head' && method_exists($instance, 'get')) {
			if ($this->debugSuccess) {
				Log::debug("HTTP: head {$cls}->get() with body stripped");
			}

			return $this->executeHeadAsGet($instance);
		}

		// __call fallback (existing behavior — preserved for controllers that implement it)
		if (method_exists($instance, '__call')) {
			if ($this->debugSuccess) {
				Log::debug("HTTP: miss {$cls}->{$this->method}(), exec __call()");
			}

			return $instance->{$this->method}();
		}

		// Controller is resolved but doesn't implement the requested verb — 405, not 404.
		if ($this->debugFailure) {
			Log::debug("HTTP: fail {$cls}->{$this->method}() not implemented, method not allowed");
		}

		throw new MethodNotAllowedException(
			'Method not allowed',
			0,
			null,
			$this->discoverAllowedMethods($instance)
		);
	}

	/**
	 * Reflect on the controller to find which HTTP-verb methods it implements. If `get()` exists, `head()` is
	 * implicitly available via the HEAD-falls-back-to-GET path, so HEAD is included even when not explicitly
	 * defined. Returned values are uppercased, suitable for the `Allow:` response header.
	 *
	 * @param HttpController $instance
	 *
	 * @return array<int, string>
	 */
	private function discoverAllowedMethods(HttpController $instance): array
	{
		$allowed = [];
		foreach (self::HTTP_VERBS as $verb) {
			if (method_exists($instance, $verb)) {
				$allowed[] = strtoupper($verb);
			}
		}

		if (in_array('GET', $allowed, true) && !in_array('HEAD', $allowed, true)) {
			$allowed[] = 'HEAD';
		}

		return $allowed;
	}

	/**
	 * Execute get() in response to a HEAD request, sending its headers but discarding its body.
	 *
	 * Headers are sent via PHP's header() function which bypasses output buffering, so the GET-equivalent
	 * headers (including Content-Length computed from the body that would have been written) reach the client
	 * unchanged. The body is captured by an outer ob_start with chunk_size=1 and a callback that returns the
	 * empty string — each chunk is discarded immediately rather than accumulated, so streaming responses
	 * (FileDownload, ContentDownload) don't buffer their full payload in memory.
	 *
	 * The response is flushed inside this method; the caller returns null so that Application's dispatcher
	 * does not double-flush.
	 *
	 * @param HttpController $instance
	 *
	 * @return null
	 */
	private function executeHeadAsGet(HttpController $instance): mixed
	{
		$obLevel = ob_get_level();
		ob_start(static fn(string $_buffer): string => '', 1);

		try {
			$response = $instance->get();
			if ($response instanceof AbstractResponse) {
				$response->flush();
			} elseif ($response !== null) {
				print $response;
			}
		} finally {
			// Defensive: only close buffers we opened, in case the controller leaked any.
			while (ob_get_level() > $obLevel) {
				ob_end_clean();
			}
		}

		return null;
	}

	/**
	 * Instantiate $className with the standard constructor data, or return null if the class is not instantiable
	 * (abstract, private constructor, interface, etc.). Uses {@see ReflectionClass::isInstantiable()} instead of
	 * catching a thrown `\Error` and string-matching its message.
	 *
	 * @param string $className
	 * @param string|null $segment
	 *
	 * @return HttpController|null
	 */
	private function tryInstantiate(string $className, ?string $segment): ?HttpController
	{
		$reflection = new ReflectionClass($className);
		if (!$reflection->isInstantiable()) {
			if ($this->debugFailure) {
				Log::debug("HTTP: skip {$className}, not instantiable");
			}
			return null;
		}

		return new $className($this->constructConstructor($segment));
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
