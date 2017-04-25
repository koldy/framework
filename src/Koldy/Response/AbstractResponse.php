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
    protected $workBeforeResponse = [];

    /**
     * The function(s) that will be called when script closes client's connection
     *
     * @var Closure[]
     */
    protected $workAfterResponse = [];

    /**
     * The array of headers that will be printed before outputting anything
     *
     * @var array
     */
    private $headers = [];

    /**
     * The HTTP status code
     *
     * @var int
     */
    private $statusCode = 200;

    /**
     * Set response header
     *
     * @param string $name
     * @param string|int|float $value [optional]
     *
     * @return AbstractResponse
     */
    public function setHeader(string $name, $value = null): AbstractResponse
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
    public function hasHeader($name): bool
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
     * @return AbstractResponse
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
     * @return AbstractResponse
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
     * @return AbstractResponse
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
     * Set the function for before flush
     *
     * @param Closure $function
     *
     * @throws \InvalidArgumentException
     * @return \Koldy\Response\AbstractResponse
     */
    public function before(Closure $function): AbstractResponse
    {
        $this->workBeforeResponse[] = $function;
        return $this;
    }

    /**
     */
    protected function runBeforeFlush(): void
    {
        foreach ($this->workBeforeResponse as $fn) {
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
     *
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
            call_user_func($fn, $this);
        }
    }

    /**
     * Set the function for after work. If needed, add more than once.
     *
     * @param Closure $function
     *
     * @throws Exception
     * @return \Koldy\Response\AbstractResponse
     */
    public function after(Closure $function): AbstractResponse
    {
        $this->workAfterResponse[] = $function;
        return $this;
    }

}
