<?php declare(strict_types=1);

namespace Koldy\Response;

use Closure;
use Koldy\Log;
use Koldy\Response\Exception as ResponseException;
use Koldy\Session;

/**
 * Every return from controller's method must return instance that extends this class
 */
abstract class AbstractResponse
{

	/**
	 * The function(s) that will be called when before script flushes the content
	 *
	 * @var Closure[]
	 */
	protected array $workBeforeResponse = [];

	/**
	 * Array of names "before" functions to execute
	 *
	 * @var string[]
	 */
	protected array $workBeforeIndex = [];

	/**
	 * Array of names "before" functions to execute
	 *
	 * @var Closure[]
	 */
	protected array $workAfterResponse = [];

	/**
	 * Ability to define name of the executing function
	 *
	 * @var string[]
	 */
	protected array $workAfterIndex = [];

	/**
	 * The array of headers that will be printed before outputting anything
	 *
	 * @var array
	 */
	protected array $headers = [];

	/**
	 * The HTTP status code
	 *
	 * @var int
	 */
	protected int $statusCode = 200;

	/**
	 * Set response header
	 *
	 * @param string $name
	 * @param string|int|float|null $value [optional]
	 *
	 * @return AbstractResponse
	 */
	public function setHeader(string $name, string|int|float|null $value = null): AbstractResponse
	{
		$this->headers[] = [
			'one-line' => ($value === null),
			'name' => $name,
			'value' => $value
		];

		return $this;
	}

	/**
	 * Is header already set
	 *
	 * @param string $name
	 *
	 * @return boolean
	 */
	public function hasHeader(string $name): bool
	{
		foreach ($this->headers as $header) {
			if (!$header['one-line'] && $header['name'] == $name) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Remove the header by name and was it removed or not
	 *
	 * @param string $name
	 *
	 * @return static
	 */
	public function removeHeader(string $name): AbstractResponse
	{
		foreach ($this->headers as $index => $header) {
			if ($header['name'] == $name) {
				unset($this->headers[$index]);
			}
		}

		return $this;
	}

	/**
	 * Remove the header by name and was it removed or not
	 *
	 * @param string $name
	 *
	 * @return string
	 * @throws Exception
	 */
	public function getHeader(string $name): string
	{
		foreach ($this->headers as $index => $header) {
			if ($header['name'] == $name) {
				return $this->headers[$index];
			}
		}

		throw new ResponseException('Unable to retrieve header name=' . $name);
	}

	/**
	 * Remove all headers
	 *
	 * @return static
	 */
	public function removeHeaders(): AbstractResponse
	{
		$this->headers = [];
		return $this;
	}

	/**
	 * Get the array of all headers (one item is one header)
	 *
	 * DO NOT USE THIS data for flushing the headers later! If you want to
	 * flush the headers, use flushHeaders() method!
	 *
	 * @return array
	 */
	public function getHeaders(): array
	{
		$headers = [];

		foreach ($this->headers as $header) {
			$headers[] = $header['one-line'] ? $header['value'] : "{$header['name']}: {$header['value']}";
		}

		return $headers;
	}

	/**
	 * Set the HTTP response header with status code
	 *
	 * @param int $statusCode
	 *
	 * @return static
	 */
	public function statusCode(int $statusCode): AbstractResponse
	{
		if ($statusCode < 100 || $statusCode > 999) {
			throw new \InvalidArgumentException('Invalid HTTP code while setting HTTP header');
		}

		$this->statusCode = $statusCode;
		return $this;
	}

	/**
	 * Get the HTTP response code that will be used when object is flushed.
	 *
	 * @return int
	 */
	public function getStatusCode(): int
	{
		return $this->statusCode;
	}

	/**
	 * Flush the headers
	 */
	public function flushHeaders(): void
	{
		if (!headers_sent()) {
			// first flush the HTTP header first, if any

			if ($this->statusCode !== 200) {
				http_response_code($this->statusCode);
			}

			foreach ($this->headers as $header) {
				if ($header['one-line']) {
					header("{$header['name']}");
				} else {
					header("{$header['name']}: {$header['value']}");
				}
			}
		} else {
			Log::warning('Can\'t flushHeaders because headers are already sent');
		}
	}

	/**
	 * Set the function to execute BEFORE flushing output buffer. If needed, add more than once and if you want,
	 * add custom name for each function.
	 *
	 * @param Closure $function
	 * @param string|null $name
	 *
	 * @return static
	 */
	public function before(Closure $function, string|null $name = null): AbstractResponse
	{
		$this->workBeforeResponse[] = $function;
		$this->workBeforeIndex[] = $name;
		return $this;
	}

	/**
	 * Is there "before" function registered with given name
	 *
	 * @param string $name
	 *
	 * @return bool
	 */
	public function hasBeforeFunction(string $name): bool
	{
		return in_array($name, $this->workBeforeIndex);
	}

	/**
	 * Count how many functions was added to "before response" with given name, or functions without name (with NULL)
	 *
	 * @param string|null $withName
	 *
	 * @return int
	 */
	public function countBeforeFunctions(string|null $withName = null): int
	{
		$counter = 0;

		foreach ($this->workBeforeIndex as $functionName) {
			if ($withName === $functionName) {
				$counter++;
			}
		}

		return $counter;
	}

	/**
	 * @throws Exception
	 */
	protected function runBeforeFlush(): void
	{
		foreach ($this->workBeforeResponse as $fn) {
			// function call is not wrapped in try/catch because we want the exception to pass through;
			// with this in mind, you'll immediately see the error
			call_user_func($fn, $this);
		}
	}

	/**
	 * Prepare flush - override if needed
	 */
	protected function prepareFlush(): void
	{
	}

	/**
	 * Flush the content to output buffer
	 */
	abstract public function flush(): void;

	/**
	 * @throws \Koldy\Exception
	 */
	protected function runAfterFlush(): void
	{
		if (isset($_SESSION)) {
			// close writing to session, since this code will run after client's connection to server has ended
			Session::close();
		}

		if (function_exists('fastcgi_finish_request')) {
			fastcgi_finish_request();
		}

		foreach ($this->workAfterResponse as $fn) {
			// function call is not wrapped in try/catch because we want the exception to pass through;
			// with this in mind, you'll immediately see the error
			call_user_func($fn, $this);
		}
	}

	/**
	 * Set the function to execute AFTER flushing output buffer. If needed, add more than once and if you want,
	 * add custom name for each function.
	 *
	 * @param Closure $function
	 * @param string|null $name
	 *
	 * @return static
	 */
	public function after(Closure $function, string|null $name = null): AbstractResponse
	{
		$this->workAfterResponse[] = $function;
		$this->workAfterIndex[] = $name;
		return $this;
	}

	/**
	 * Is there "after" function registered with given name
	 *
	 * @param string $name
	 *
	 * @return bool
	 */
	public function hasAfterFunction(string $name): bool
	{
		return in_array($name, $this->workAfterIndex);
	}

	/**
	 * Count how many functions was added to "after response" with given name, or functions without name (with NULL)
	 *
	 * @param string|null $withName
	 *
	 * @return int
	 */
	public function countAfterFunctions(string|null $withName = null): int
	{
		$counter = 0;

		foreach ($this->workAfterIndex as $functionName) {
			if ($withName === $functionName) {
				$counter++;
			}
		}

		return $counter;
	}

}
