<?php declare(strict_types=1);

namespace Koldy\Response;

use Koldy\Application;
use Koldy\Data;

/**
 * The JSON class. Feel free to override it if you need to make it work different.
 *
 * @link http://koldy.net/docs/json
 * @phpstan-consistent-constructor
 */
class Json extends AbstractResponse
{

    use Data;

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
     * @link http://koldy.net/docs/json#usage
     */
    public static function create(array $data = []): Json
    {
        return new static($data);
    }

    /**
     * If you try to print your JSON object instance, you'll get JSON encoded string
     *
     * @return string
     */
    public function __toString()
    {
        return json_encode($this->getData());
    }

    /**
     * @link http://koldy.net/docs/json#usage
     * @throws \Koldy\Exception
     */
    public function flush(): void
    {
        $this->prepareFlush();
        $this->runBeforeFlush();

        $content = json_encode($this->getData());

	    $statusCode = $this->statusCode;
	    $statusCodeIs1XX = $statusCode >= 100 && $statusCode <= 199;

	    if (!$statusCodeIs1XX) {
			if ($statusCode === 204) {
				// there is no content to output
				$this->setHeader('Content-Length', 0);
				$content = ''; // no matter if there's something in $content, we'll output nothing
			} else {
				// otherwise, there should be some content in application/json response
				// PHP serializes empty array into "[]", so we'll return "{}" instead in that case

				if (count($this->getData()) === 0) {
					// if there is no data, then we'll output empty JSON object
					$content = '{}';
					$this->setHeader('Content-Length', 2);
				} else {
					$size = mb_strlen($content, Application::getEncoding());
					$this->setHeader('Content-Length', $size);
				}
			}
	    } // in case of 1XX status, you should handle specific headers by yourself

        $this->flushHeaders();

		@ob_start();
		if ($content !== '') {
			// print content ONLY if status code is not 204 (No Content)
			print $content;
		}
		@ob_flush();

        $this->runAfterFlush();
    }

}
