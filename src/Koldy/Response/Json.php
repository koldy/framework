<?php declare(strict_types=1);

namespace Koldy\Response;

use Koldy\Data;

/**
 * The JSON class. Feel free to override it if you need to make it work different.
 *
 * @link http://koldy.net/docs/json
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
        $this->data = $data;
    }

    /**
     * Create the object with initial data
     *
     * @param array $data [optional]
     *
     * @return Json
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
     */
    public function flush(): void
    {
        $this->prepareFlush();
        $this->runBeforeFlush();

        ob_start();

        print json_encode($this->getData());

        $size = ob_get_length();
        $this->setHeader('Content-Length', $size);

        $this->flushHeaders();
        ob_end_flush();
        flush();

        $this->runAfterFlush();
    }

}
