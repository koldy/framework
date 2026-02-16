<?php declare(strict_types=1);

namespace Koldy\Route;

use InvalidArgumentException;
use Koldy\Application;
use Koldy\Exception;
use Koldy\Log;
use Koldy\Request;
use Koldy\Response\AbstractResponse;
use Koldy\Response\Exception\NotFoundException;
use Koldy\Response\Plain;
use Koldy\Response\ResponseExceptionHandler;
use Koldy\Util;
use Throwable;

/**
 * HttpRoute is new HTTP router that is using strictly namespaced PHP classes.
 *
 * The DefaultRouter that was standard in Koldy relied on PHP include path system to determine which class and which
 * action would be executed. That approach was fine back in 2010s, but it's not anymore.
 *
 * To minimize disk reads, this router relies only on PHP's built-in autoloader and class resolution.
 *
 * Unlike other PHP frameworks only that forces you do define all possible routes in one place (or via annotations or
 * by any other approach), the HTTP router in Koldy Framework will try to resolve the class and method based on the URI.
 * But, unlike others, it will process each URI segment separately and when first class and method returns some
 * response, it will stop processing and return the response. This approach allows us to have literally unlimited
 * number of routes with no extra maintenance nor overhead. Imagine having 2000 routes defined in one place - any
 * approach that other frameworks are using would start getting slower and slower.
 *
 * With this approach, you can have routes such as /company/{name}/invoices/{uuid}/files and by natural order, you
 * don't want to end up in "files controller" directly, unless you really have an access to company and access to that
 * invoice specifically. HttpRouter will basically go like this:
 *
 * 1. enter CompanyHttp
 * 2. resolve {name}, load company, check access and store company in context
 * 3. enter Company/InvoicesHttp
 * 4. resolve {uuid}, load invoice, check access and store invoice in context
 * 5. enter Company/Invoices/FilesHttp and execute the method or execute something else
 *
 * This approach is not only fast, but also very flexible, intuitive, easy to use and allows handling of unlimited
 * number of routes.
 *
 * To use this router, you need to set the "routing_class" in config/application.php to "Koldy\\Route\\HttpRoute",
 * then set "routing_options" to something like this:
 *
 * ```php
 * 'routing_options' => [
 *   'path' => APPLICATION_PATH . '/http/',
 *   'namespace' => 'App\\Http\\'
 * ]
 * ```
 *
 * And then register App\Http namespace in your composer.json and set it to the same path as "routing_options.path"
 * option.
 *
 * @template TContext of array
 *
 * @extends AbstractRoute<array{path: string, namespace: string}>
 */
class HttpRoute extends AbstractRoute
{

	/**
	 * The original URI as it was given to the router
	 */
	protected string|null $uri = null;

	/**
	 * The array of URI parts
	 */
	protected array|null $uriParts = null;

	/**
	 * The pointer to the current position in the URI array
	 */
	private int $pointer = 0;

	/**
	 * The HTTP method
	 */
	protected string|null $method = null;

	/**
	 * The path to the file system where the HTTP controllers are located
	 */
	protected string|null $fileSystemRoute = null;

	/**
	 * The namespace where the HTTP controllers are located
	 */
	protected string|null $namespace = null;

	protected string|null $classPrefix = null;

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
	 * @throws Exception
	 */
	public function start(string $uri): mixed
	{
		// the incoming $uri MUST start with "/"

		if (!str_starts_with($uri, '/')) {
			throw new InvalidArgumentException('URI must start with slash');
		}

		$url = parse_url($uri);

		if (!is_array($url) || !array_key_exists('path', $url)) {
			throw new InvalidArgumentException('Invalid URI given');
		}

		$path = $url['path'];
		$query = $url['query'] ?? '';

		while (str_contains($path, '//')) {
			$path = str_replace('//', '/', $path);
		}

		$path = strtolower($path);
		$path = trim($path);

		$method = strtolower(Request::method());
		$isGet = $method === 'get' || $method === 'head';
		if ($isGet && str_ends_with($path, '/') && strlen($path) > 1) {
			// cut the trailing slash
			$path = substr($path, 0, -1);

			$newUri = $path;
			if ($query !== '') {
				$newUri .= "?{$query}";
			}

			header("Location: {$newUri}", true, 301);
			exit(0);
		}

		if (!isset($this->config['path'])) {
			Application::terminateWithError('The application routing_options.path has to be string');
		}

		// @phpstan-ignore-next-line
		if (!isset($this->config['namespace'])) {
			Application::terminateWithError('The application routing_options.namespace has to be string');
		}

		$fileSystemPath = $this->config['path'];
		$namespace = $this->config['namespace'];

		$self = new self($this->config);
		$self->uriParts = explode('/', substr($path, 1));
		$self->uri = $uri;
		$self->fileSystemRoute = $fileSystemPath;
		$self->namespace = $namespace;
		$self->classPrefix = $namespace;
		$self->method = $method;

		try {
			$response = $self->exec();
		} catch (Throwable $e) {
			$self->handleException($e);
			exit(1);
		}

		return $response;
	}

	private function sanitizeSegment(string $segment): string
	{
		$segment = Util::slug($segment);

		while (str_contains($segment, '--')) {
			$segment = str_replace('--', '-', $segment);
		}

		$segment = str_replace('-', ' ', $segment);
		$segment = ucwords($segment);
		return str_replace(' ', '', $segment);
	}

	public function exec(): mixed
	{
		$segment = $this->sanitizeSegment($this->uriParts[$this->pointer] ?? '');
		$className = $this->classPrefix . $segment . 'Http';
		$hasClass = class_exists($className);

		if ($hasClass) {
			if (is_subclass_of($className, self::class)) {
				$controller = new $className();
				$this->bindThis($controller);

				$nextSegment = count($this->uriParts) > $this->pointer + 1 ? $this->uriParts[$this->pointer + 1] : null;
				$nextSegment = $this->sanitizeSegment($nextSegment ?? '');

				$method = $this->method . ucfirst($nextSegment);

				if (method_exists($controller, $method)) {
					$return = $controller->$method();
				} else if (method_exists($controller, '__call')) {
					$return = $controller->__call($method, []);
				} else {
					Log::debug("HttpRoute: Method {$method} does not exist in class={$className} on {$this->method}={$this->uri}");
					throw new NotFoundException('Endpoint not found');
				}

				if ($return instanceof self) {
					return $return->exec();
				}

				if ($return instanceof AbstractResponse) {
					return $return;
				}

				return Plain::create((string)$return);
			} else {
				// 503 - invalid class
				Log::debug("HttpRoute: Class {$className} does not extend HttpRoute on {$this->method}={$this->uri}");
				Application::terminateWithError('Class does not extend HttpRoute');
			}
		} else {
			// no class found, ... but, we might need to continue processing - it depends on number of segments
			if (count($this->uriParts) > $this->pointer + 1) {
				// so, we have more segments to process, so let's try the next one by moving pointer to the next segment
				return $this->nextSegment()->exec();
			}

			// 404
			Log::debug("HttpRoute: Class {$className} does not exist for {$this->method}={$this->uri}");
			throw new NotFoundException('Endpoint not found');
		}
	}

	protected function nextSegment(int $howMany = 1): static
	{
		$segment = $this->sanitizeSegment($this->uriParts[$this->pointer] ?? '');
		$this->classPrefix .= $segment . '\\';
		$this->pointer += $howMany;
		return $this;
	}

	protected function bindThis(self $instance): void
	{
		$instance->uri = $this->uri;
		$instance->uriParts = $this->uriParts;
		$instance->pointer = $this->pointer;
		$instance->method = $this->method;
		$instance->fileSystemRoute = $this->fileSystemRoute;
		$instance->namespace = $this->namespace;
		$instance->classPrefix = $this->classPrefix;
		$instance->context = $this->context;
	}

	public function handleException(Throwable $e): void
	{
		$exceptionHandlerClassName = "{$this->namespace}ExceptionHandler";

		if (class_exists($exceptionHandlerClassName) && method_exists($exceptionHandlerClassName, 'exec')) {
			$exceptionHandler = new $exceptionHandlerClassName($e);
			$exceptionHandler->exec();
		} else {
			$exceptionHandler = new ResponseExceptionHandler($e);
			$exceptionHandler->exec();
		}
	}
}
