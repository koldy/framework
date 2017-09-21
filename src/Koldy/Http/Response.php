<?php declare(strict_types=1);

namespace Koldy\Http;

/**
 * This will be the instance of the response created by \Koldy\Http\Request class
 *
 */
class Response
{

    /**
     * @var resource
     */
    protected $ch;

    /**
     * The response body from request
     *
     * @var string
     */
    protected $body;

    /**
     * @var null
     */
    protected $headersText = null;

    /**
     * @var Request
     */
    protected $request;

    /**
     * Response constructor.
     *
     * @param resource $ch
     * @param mixed $body
     * @param Request $request
     */
    public function __construct($ch, $body, Request $request)
    {
        $this->ch = $ch;
        $this->request = $request;

        $headerSize = $this->headerSize();

        if ($headerSize == 0) {
            $this->body = $body;
        } else {
            $this->headersText = trim(substr((string)$body, 0, $headerSize));
            $this->body = substr((string)$body, $headerSize);
        }
    }

    public function __destruct()
    {
        curl_close($this->ch);
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
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
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
     * Is response OK? (is HTTP response code 200)
     *
     * @return boolean
     */
    public function isSuccess(): bool
    {
        return $this->getHttpCode() == 200;
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
     * Get the request's connect time in seconds
     *
     * @return float
     */
    public function getConnectTime(): float
    {
        return curl_getinfo($this->ch, CURLINFO_CONNECT_TIME);
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
     * Get the request total time in seconds
     *
     * @return float
     */
    public function getTotalTime(): float
    {
        return curl_getinfo($this->ch, CURLINFO_TOTAL_TIME);
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
     * @return int
     */
    public function headerSize(): int
    {
        return (int)curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
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
     * @param bool $allDetails
     *
     * @return string
     */
    public function debug($allDetails = false)
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
            if (strlen($body) > 255) {
                $body = substr($body, 0, 252) . '...';
            }

            $msg .= "\n";
            $msg .= "RESPONSE BODY:----------------------------\n";
            $msg .= $body . "\n";
            $msg .= '------------------------------------------';
        }

        return $msg;
    }

    /**
     * If you try to print the response object, you'll get response body
     *
     * @return string
     */
    public function __toString()
    {
        return $this->debug();
    }

}
