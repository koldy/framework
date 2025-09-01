<?php declare(strict_types=1);

namespace Koldy\Http;

use CurlHandle;
use Koldy\Application;

/**
 * This will be the instance of the response created by \Koldy\Http\Request class
 *
 */
class Response
{

	/**
	 * @var CurlHandle
	 */
	protected CurlHandle $ch;

	/**
	 * The response body from request
	 *
	 * @var string
	 */
	protected string $body;

	/**
	 * @var string|null
	 */
	protected string|null $headersText = null;

	/**
	 * @var array|null
	 */
	protected array|null $headers = null;

	/**
	 * @var Request
	 */
	protected Request $request;

	/**
	 * Response constructor.
	 *
	 * @param CurlHandle $ch
	 * @param string $body
	 * @param Request $request
	 */
	public function __construct(CurlHandle $ch, string $body, Request $request)
	{
		$this->ch = $ch;
		$this->request = $request;

		$headerSize = $this->headerSize();

		if ($headerSize == 0) {
			$this->body = $body;
		} else {
			$this->headersText = trim(substr($body, 0, $headerSize));
			$this->body = substr($body, $headerSize);
		}
	}

	/**
	 * Get length of headers text
	 *
	 * @return int
	 */
	public function headerSize(): int
	{
		return (int)curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
	}

	public function __destruct()
	{
		curl_close($this->ch);
	}

	/**
	 * Is response OK? (is HTTP response code 200)
	 *
	 * @return boolean
	 */
	public function isSuccess(): bool
	{
		return $this->getHttpCode() >= 200 && $this->getHttpCode() <= 299;
	}

	/**
	 * What is the response HTTP code?
	 *
	 * @return int
	 */
	public function getHttpCode(): int
	{
		return (int)curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
	}

	/**
	 * Get the content type of response
	 *
	 * @return string
	 */
	public function getContentType(): string
	{
		return curl_getinfo($this->ch, CURLINFO_CONTENT_TYPE);
	}

	/**
	 * Get the request's connect time in milliseconds
	 *
	 * @return int
	 */
	public function getConnectTimeMs(): int
	{
		return (int)round($this->getConnectTime() * 1000);
	}

	/**
	 * Get the request's connect time in seconds
	 *
	 * @return float
	 */
	public function getConnectTime(): float
	{
		return curl_getinfo($this->ch, CURLINFO_CONNECT_TIME);
	}

	/**
	 * Get the request total time in milliseconds
	 *
	 * @return int
	 */
	public function getTotalTimeMs(): int
	{
		return (int)round($this->getTotalTime() * 1000);
	}

	/**
	 * Get the request total time in seconds
	 *
	 * @return float
	 */
	public function getTotalTime(): float
	{
		return curl_getinfo($this->ch, CURLINFO_TOTAL_TIME);
	}

	/**
	 * Get the headers as text
	 *
	 * @return null|string
	 */
	public function getHeadersText(): ?string
	{
		return $this->headersText;
	}

	/**
	 * Is there response header with the given name
	 *
	 * @param string $name
	 *
	 * @return bool
	 */
	public function hasHeader(string $name): bool
	{
		return $this->getHeader($name) !== null;
	}

	/**
	 * Get the header with the given name
	 *
	 * @param string $name
	 *
	 * @return string|null
	 */
	public function getHeader(string $name): ?string
	{
		return $this->getHeaders()[$name] ?? null;
	}

	/**
	 * Get all response headers as array where key is the header name and value is header value
	 *
	 * @return array
	 */
	public function getHeaders(): array
	{
		if ($this->headers === null && $this->headersText !== null) {
			$this->headers = [];

			foreach (explode("\n", $this->headersText) as $line) {
				$pos = strpos($line, ':');

				if ($pos === false) {
					// this is one-line header
					$this->headers[$line] = null;
				} else {
					$name = substr($line, 0, $pos);
					$value = substr($line, $pos + 1);
					$this->headers[$name] = trim($value);
				}
			}
		}

		return $this->headers ?? [];
	}

	/**
	 * Get the object that was used for Request
	 *
	 * @return Request
	 */
	public function getRequest(): Request
	{
		return $this->request;
	}

	/**
	 * If you try to print the response object, you'll get response body
	 *
	 * @return string
	 * @throws \Koldy\Exception
	 */
	public function __toString()
	{
		return $this->debug();
	}

	/**
	 * @param bool $allDetails
	 *
	 * @return string
	 * @throws \Koldy\Exception
	 */
	public function debug(bool $allDetails = false): string
	{
		$className = get_class($this);
		$msg = "{$className} ({$this->getHttpCode()}) {$this->request->getMethod()}={$this->getUrl()} IN {$this->getTotalTime()}s";

		if ($allDetails) {
			if ($this->headersText != null) {
				$msg .= " with response HEADERS:\n";
				foreach (explode("\n", $this->headersText) as $line) {
					$msg .= "\t{$line}\n";
				}
			} else {
				$msg .= "\n";
			}

			$body = $this->getBody();
			if (mb_strlen($body, Application::getEncoding()) > 255) {
				$body = mb_substr($body, 0, 252, Application::getEncoding()) . '...';
			}

			$msg .= "\n";
			$msg .= "RESPONSE BODY:----------------------------\n";
			$msg .= $body . "\n";
			$msg .= '------------------------------------------';
		}

		return $msg;
	}

	/**
	 * What was the final request URL? If request was redirected, this will return final URL
	 *
	 * @return string
	 */
	public function getUrl(): string
	{
		return curl_getinfo($this->ch, CURLINFO_EFFECTIVE_URL);
	}

	/**
	 * Get the response body
	 *
	 * @return string
	 */
	public function getBody(): string
	{
		return $this->body;
	}

}
