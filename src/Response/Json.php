<?php declare(strict_types=1);

namespace Koldy\Response;

use Koldy\Application;
use Koldy\Data;
use Koldy\Json as KoldyJson;
use Stringable;

/**
 * The JSON class. Feel free to override it if you need to make it work different.
 *
 * @link http://koldy.net/docs/json
 * @phpstan-consistent-constructor
 */
class Json extends AbstractResponse implements Stringable
{

	use Data;

	/**
	 * Although we can detect if array is associative or not, we can't detect it if array is empty, so we have a flag.
	 * By default, we serialize all arrays as JSON objects (associative), but you can change it to false if you have
	 * some advanced use case.
	 */
	private bool $isAssociative = true;

	/**
	 * Alternatively you can construct this object with a string that will just get flushed later on.
	 * This is advanced usage of JSON that solves the problems of encode-decode-encode cases in PHP, during
	 * which types could be lost (because both "{}" and "[]" encodes into "[]").
	 *
	 * If this value is set, it will take precedence over the internal array $data, which will be ignored in this case
	 */
	protected string|null $content = null;

	/**
	 * Json constructor.
	 *
	 * @param array $data
	 */
	public function __construct(array $data = [])
	{
		$this->setHeader('Content-Type', 'application/json');
		$this->setData($data);
	}

	/**
	 * Create the object with initial data
	 *
	 * @param array $data [optional]
	 *
	 * @return static
	 * @link http://koldy.net/docs/utilities
	 */
	public static function create(array $data = []): Json
	{
		return new static($data);
	}

	/**
	 * Sets JSON string content directly, bypassing internal array $data structure. Should be used only in special cases
	 *  when you want to preserve some special JSON types.
	 *
	 * @param string $jsonString
	 *
	 * @return static
	 */
	public static function fromString(string $jsonString): static
	{
		return static::create()->setContent($jsonString);
	}

	/**
	 * Sets JSON string content directly, bypassing internal array $data structure. Should be used only in special cases
	 * when you want to preserve some special JSON types.
	 *
	 * @param string $content
	 *
	 * @return $this
	 */
	public function setContent(string $content): static
	{
		$this->content = $content;
		return $this;
	}

	/**
	 * Returns internal content previously set with fromString() or with setContent() method.
	 */
	public function getContent(): string
	{
		return $this->content;
	}

	/**
	 * Sets if array should be serialized as associative (object) or not (array). This is useful only when there's
	 * an empty array to be serialized.
	 *
	 * @param bool $isAssociative
	 *
	 * @return $this
	 */
	public function setAssociativeArray(bool $isAssociative): Json
	{
		$this->isAssociative = $isAssociative;
		return $this;
	}

	/**
	 * If you try to print your JSON object instance, you'll get JSON encoded string
	 *
	 * @return string
	 */
	public function __toString(): string
	{
		return json_encode($this->getData());
	}

	public function getOutput(): mixed
	{
		if ($this->statusCode === 204) {
			return '';
		}

		return json_encode($this->getData());
	}

	/**
	 * @link http://koldy.net/docs/utilities
	 * @throws \Koldy\Exception
	 */
	public function flush(): void
	{
		$this->prepareFlush();
		$this->runBeforeFlush();

		$content = $this->content ?? KoldyJson::encode($this->getData());

		$statusCode = $this->statusCode;
		$statusCodeIs1XX = $statusCode >= 100 && $statusCode <= 199;

		if (!$statusCodeIs1XX) {
			if ($statusCode === 204) {
				// there is no content to output
				$this->setHeader('Content-Length', 0);
				$content = ''; // no matter if there's something in $content, we'll output nothing
			} else {
				// otherwise, there should be some content in application/json response
				// PHP serializes empty array into "[]", so we'll return "{}" instead in that case, but depending on isAssociative flag

				if ($this->content !== null) {
					$size = mb_strlen($content, Application::getEncoding());
					$this->setHeader('Content-Length', $size);
				} else {
					if (count($this->getData()) === 0) {
						// if there is no data, then we'll output empty JSON object
						$content = $this->isAssociative ? '{}' : '[]';
						$this->setHeader('Content-Length', 2);
					} else {
						$size = mb_strlen($content, Application::getEncoding());
						$this->setHeader('Content-Length', $size);
					}
				}
			}
		} // in case of 1XX status, you should handle specific headers by yourself

		$this->flushHeaders();

		if ($content !== '') {
			// print content ONLY if status code is not 204 (No Content)
			print $content;
		}

		$this->runAfterFlush();
	}

}
