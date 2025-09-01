<?php declare(strict_types=1);

namespace Koldy\Http;

use CURLFile;
use Koldy\Http\Exception as HttpException;
use Koldy\Json;
use Koldy\Util;

/**
 * Make HTTP request to any given URL.
 * This class requires PHP CURL extension!
 */
class Request
{

	public const GET = 'GET';
	public const POST = 'POST';
	public const PUT = 'PUT';
	public const DELETE = 'DELETE';
	public const HEAD = 'HEAD';
	public const PATCH = 'PATCH';
	public const OPTIONS = 'OPTIONS';

	/**
	 * @var string
	 */
	protected string $url;

	/**
	 * @var array
	 */
	protected array $params = [];

	/**
	 * @var string
	 */
	protected string $method = 'GET';

	/**
	 * The CURL options
	 *
	 * @var array
	 */
	protected array $options = [];

	/**
	 * Request headers
	 *
	 * @var array
	 */
	protected array $headers = [];

	/**
	 * Make quick GET request
	 *
	 * @param string $url
	 * @param array|null $params
	 * @param array|null $headers
	 *
	 * @return Response
	 * @throws Exception
	 * @throws Json\Exception
	 * @throws \Koldy\Exception
	 */
	public static function get(string $url, array|null $params = null, array|null $headers = null): Response
	{
		return static::quickRequest($url, self::GET, $params, $headers);
	}

	/**
	 * @param string $url
	 * @param string $method
	 * @param array|null $params
	 * @param array|null $headers
	 *
	 * @return Response
	 * @throws Exception
	 * @throws Json\Exception
	 * @throws \Koldy\Exception
	 */
	protected static function quickRequest(
		string $url,
		string $method,
		array|null $params = null,
		array|null $headers = null
	): Response {
		// @phpstan-ignore-next-line due to @phpstan-consistent-constructor
		$self = new static();
		$self->setUrl($url)->setMethod($method);

		if ($params != null) {
			$self->setParams($params);
		}

		if ($headers != null) {
			$self->setHeaders($headers);
		}

		$self->setOption(CURLOPT_FOLLOWLOCATION, true);
		$self->setOption(CURLOPT_MAXREDIRS, 10);

		return $self->exec();
	}

	/**
	 * Set the CURL option
	 *
	 * @param int $name
	 * @param mixed $value
	 *
	 * @return static
	 * @link http://php.net/manual/en/function.curl-setopt.php
	 */
	public function setOption(int $name, mixed $value): static
	{
		$this->options[$name] = $value;
		return $this;
	}

	/**
	 * Execute request
	 * @return Response
	 * @throws Exception
	 * @throws Json\Exception
	 * @throws \Koldy\Exception
	 */
	public function exec(): Response
	{
		$url = $this->getUrl();

		$ch = curl_init($url);

		if ($ch === false) {
			throw new HttpException('Can not construct CURL Handle class; please check if you have cURL installed');
		}

		foreach ($this->getCurlOptions() as $option => $value) {
			// iterating so we have all options applied in given order
			curl_setopt($ch, $option, $value);
		}

		$body = curl_exec($ch);

		if (curl_errno($ch)) {
			throw new HttpException(curl_error($ch));
		}

		if (is_bool($body)) {
			throw new HttpException('cURL exec returned boolean (' . ($body ? 'TRUE' : 'FALSE') . ') instead of string; please check the request options');
		}

		return new Response($ch, $body, $this);
	}

	/**
	 * Get the URL on which the request will be fired
	 *
	 * @return string
	 */
	public function getUrl(): string
	{
		$url = $this->url;

		if ($this->getMethod() == self::GET) {
			if (count($this->params) > 0) {
				$params = http_build_query($this->getParams());

				if (str_contains('?', $url) && !str_ends_with($url, '?')) {
					// just add "&"
					$url .= $params;
				} else {
					$url .= '?' . $params;
				}
			}
		}
		return $url;
	}

	/**
	 * Update the request's target URL
	 *
	 * @param string $url
	 *
	 * @return static
	 */
	public function setUrl(string $url): static
	{
		$this->url = $url;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getMethod(): string
	{
		return $this->method;
	}

	/**
	 * @param string $method
	 *
	 * @return static
	 */
	public function setMethod(string $method): static
	{
		$this->method = $method;
		return $this;
	}

	/**
	 * Get all parameters that will be sent
	 * @return array
	 */
	public function getParams(): array
	{
		return $this->params;
	}

	/**
	 * Set the parameters that will be sent. Any previously set parameters will be overridden.
	 *
	 * @param array $params
	 *
	 * @return static
	 */
	public function setParams(array $params): static
	{
		$this->params = $params;
		return $this;
	}

	/**
	 * Get the prepared curl options for the HTTP request
	 *
	 * @return array
	 * @throws Json\Exception
	 * @throws \Koldy\Exception
	 */
	protected function getCurlOptions(): array
	{
		$options = [];

		$options[CURLOPT_RETURNTRANSFER] = true;
		$options[CURLOPT_HEADER] = true;

		foreach ($this->getOptions() as $option => $value) {
			$options[$option] = $value;
		}

		switch ($this->getMethod()) {
			case self::POST:
			case self::PATCH:
				if (!$this->hasOption(CURLOPT_CUSTOMREQUEST)) {
					$options[CURLOPT_CUSTOMREQUEST] = $this->getMethod();
				}

				if (!$this->hasOption(CURLOPT_POSTFIELDS)) {
					// check if there are files in the request
					$hasFileInParams = false;

					foreach ($this->getParams() as $key => $value) {
						if ($value instanceof CURLFile) {
							$hasFileInParams = true;
						}
					}

					if ($hasFileInParams) {
						$options[CURLOPT_POSTFIELDS] = $this->getParams();
					} else {
						$options[CURLOPT_POSTFIELDS] = count($this->getParams()) > 0 ? http_build_query($this->getParams()) : '';
					}
				}

				if ($this->hasHeader('Content-Type') && ($this->getHeader('Content-Type') == 'application/json' || Util::startsWith($this->getHeader('Content-Type'),
							'application/json;'))) {
					$options[CURLOPT_POSTFIELDS] = Json::encode($this->getParams());
				}
				break;

			case self::PUT:
			case self::DELETE:
			case self::HEAD:
			case self::OPTIONS:
				if (!$this->hasOption(CURLOPT_CUSTOMREQUEST)) {
					$options[CURLOPT_CUSTOMREQUEST] = $this->getMethod();
				}

				if (!$this->hasOption(CURLOPT_POSTFIELDS)) {
					$options[CURLOPT_POSTFIELDS] = count($this->params) > 0 ? http_build_query($this->params) : '';
				}
				break;
		}

		if (count($this->headers) > 0) {
			$options[CURLOPT_HTTPHEADER] = $this->getPreparedHeaders();
		}

		return $options;
	}

	/**
	 * Get all CURL options
	 *
	 * @return array
	 */
	public function getOptions(): array
	{
		return $this->options;
	}

	/**
	 * Set the array of options. Array must be valid array with CURL constants as keys
	 *
	 * @param array $curlOptions
	 *
	 * @return static
	 * @link http://php.net/manual/en/function.curl-setopt.php
	 */
	public function setOptions(array $curlOptions): static
	{
		$this->options = $curlOptions;
		return $this;
	}

	/**
	 * @param string $name
	 *
	 * @return string|null
	 */
	public function getHeader(string $name): ?string
	{
		return $this->headers[$name] ?? null;
	}

	/**
	 * @return array
	 */
	public function getPreparedHeaders(): array
	{
		if (count($this->headers) > 0) {
			$headers = [];

			foreach ($this->headers as $key => $value) {
				$headers[] = "{$key}: {$value}";
			}

			return $headers;
		}

		return [];
	}

	/**
	 * Make quick POST request
	 *
	 * @param string $url
	 * @param array|null $params
	 * @param array|null $headers
	 *
	 * @return Response
	 * @throws Exception
	 * @throws Json\Exception
	 * @throws \Koldy\Exception
	 */
	public static function post(string $url, array|null $params = null, array|null $headers = null): Response
	{
		return static::quickRequest($url, self::POST, $params, $headers);
	}

	/**
	 * Make quick PUT request
	 *
	 * @param string $url
	 * @param array|null $params
	 * @param array|null $headers
	 *
	 * @return Response
	 * @throws Exception
	 * @throws Json\Exception
	 * @throws \Koldy\Exception
	 */
	public static function put(string $url, array|null $params = null, array|null $headers = null): Response
	{
		return static::quickRequest($url, self::PUT, $params, $headers);
	}

	/**
	 * Make quick DELETE request
	 *
	 * @param string $url
	 * @param array|null $params
	 * @param array|null $headers
	 *
	 * @return Response
	 * @throws Exception
	 * @throws Json\Exception
	 * @throws \Koldy\Exception
	 */
	public static function delete(string $url, array|null $params = null, array|null $headers = null): Response
	{
		return static::quickRequest($url, self::DELETE, $params, $headers);
	}

	/**
	 * Make quick PATCH request
	 *
	 * @param string $url
	 * @param array|null $params
	 * @param array|null $headers
	 *
	 * @return Response
	 * @throws Exception
	 * @throws Json\Exception
	 * @throws \Koldy\Exception
	 */
	public static function patch(string $url, array|null $params = null, array|null $headers = null): Response
	{
		return static::quickRequest($url, self::PATCH, $params, $headers);
	}

	/**
	 * Make quick OPTIONS request
	 *
	 * @param string $url
	 * @param array|null $params
	 * @param array|null $headers
	 *
	 * @return Response
	 * @throws Exception
	 * @throws Json\Exception
	 * @throws \Koldy\Exception
	 */
	public static function options(string $url, array|null $params = null, array|null $headers = null): Response
	{
		return static::quickRequest($url, self::OPTIONS, $params, $headers);
	}

	/**
	 * Make quick HEAD request
	 *
	 * @param string $url
	 * @param array|null $params
	 * @param array|null $headers
	 *
	 * @return Response
	 * @throws Exception
	 * @throws Json\Exception
	 * @throws \Koldy\Exception
	 */
	public static function head(string $url, array|null $params = null, array|null $headers = null): Response
	{
		return static::quickRequest($url, self::HEAD, $params, $headers);
	}

	/**
	 * Set the request parameter
	 *
	 * @param string $name
	 * @param mixed $value
	 *
	 * @return static
	 */
	public function setParam(string $name, mixed $value): static
	{
		$this->params[$name] = $value;
		return $this;
	}

	/**
	 * Check if URL parameter is set
	 *
	 * @param string $key
	 *
	 * @return boolean
	 */
	public function hasParam(string $key): bool
	{
		return isset($this->params[$key]);
	}

	/**
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function getParam(string $key): mixed
	{
		return $this->params[$key] ?? null;
	}

	/**
	 * Set the request header
	 *
	 * @param string $name
	 * @param string|int|float $value
	 *
	 * @return static
	 */
	public function setHeader(string $name, string|int|float $value): static
	{
		$this->headers[$name] = $value;
		return $this;
	}

	/**
	 * Get headers that will be sent
	 * @return array
	 */
	public function getHeaders(): array
	{
		return $this->headers;
	}

	/**
	 * Set the headers that will be sent. Any previously set headers will be overridden.
	 *
	 * @param array $headers
	 *
	 * @return static
	 */
	public function setHeaders(array $headers): static
	{
		$this->headers = $headers;
		return $this;
	}

	/**
	 * @param string $name
	 *
	 * @return static
	 */
	public function removeHeader(string $name): static
	{
		if ($this->hasHeader($name)) {
			unset($this->headers[$name]);
		}
		return $this;
	}

	/**
	 * Check if URL header is set
	 *
	 * @param string $name
	 *
	 * @return boolean
	 */
	public function hasHeader(string $name): bool
	{
		return isset($this->headers[$name]);
	}

	/**
	 * @param int $option
	 *
	 * @return mixed|null
	 */
	public function getOption(int $option): mixed
	{
		return $this->options[$option] ?? null;
	}

	/**
	 * @param int $option
	 *
	 * @return static
	 */
	public function removeOption(int $option): static
	{
		if ($this->hasOption($option)) {
			unset($this->options[$option]);
		}

		return $this;
	}

	/**
	 * Check if CURL option is set (exists in options array)
	 *
	 * @param int $key
	 *
	 * @return boolean
	 */
	public function hasOption(int $key): bool
	{
		return array_key_exists($key, $this->options);
	}

	/**
	 * @return string
	 * @throws Json\Exception
	 * @throws \Koldy\Exception
	 */
	public function __toString()
	{
		return $this->debug();
	}

	/**
	 * Print settings and all values useful for troubleshooting
	 *
	 * @return string
	 * @throws Json\Exception
	 * @throws \Koldy\Exception
	 */
	public function debug(): string
	{
		$constants = get_defined_constants(true);
		$flipped = array_flip($constants['curl']);
		$curlOpts = preg_grep('/^CURLOPT_/', $flipped);
		$curlInfo = preg_grep('/^CURLINFO_/', $flipped);

		$options = [];
		foreach ($this->getCurlOptions() as $const => $value) {
			if (isset($curlOpts[$const])) {
				$options[$curlOpts[$const]] = $value;
			} else if (isset($curlInfo[$const])) {
				$options[$curlInfo[$const]] = $value;
			} else {
				$options[$const] = $value;
			}
		}

		$className = get_class($this);
		$msg = "[{$className}] to {$this->getMethod()}={$this->getUrl()}";

		if (count($this->params) > 0) {
			$params = $this->getParams();
			if ($this->getMethod() == self::POST && $this->hasHeader('Content-Type') && $this->getHeader('Content-Type') == 'application/json') {
				$params = Json::encode($params);
			} else {
				$params = http_build_query($params);
			}

			$msg .= "\nParameters: {$params}";
		}

		if (count($options) > 0) {
			$msg .= 'CURL options: ' . print_r($options, true);
		}

		return $msg;
	}

}
