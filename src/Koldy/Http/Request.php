<?php declare(strict_types=1);

namespace Koldy\Http;

use Koldy\Http\Exception as HttpException;
use Koldy\Json;

/**
 * Make HTTP request to any given URL.
 * This class requires PHP CURL extension!
 *
 */
class Request
{

    public const GET = 'GET';
    public const POST = 'POST';
    public const PUT = 'PUT';
    public const DELETE = 'DELETE';

    /**
     * @var string
     */
    protected $url = null;

    /**
     * @var string[]
     */
    protected $params = [];

    /**
     * @var string
     */
    protected $method = 'GET';

    /**
     * The CURL options
     *
     * @var array
     */
    protected $options = [];

    /**
     * Request headers
     *
     * @var array
     */
    protected $headers = [];

    /**
     * Update the request's target URL
     *
     * @param string $url
     *
     * @return Request
     */
    public function setUrl(string $url): Request
    {
        $this->url = $url;
        return $this;
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

                if (strpos('?', $url) !== false && substr($url, -1) != '?') {
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
     * @param string $method
     *
     * @return Request
     */
    public function setMethod(string $method): Request
    {
        $this->method = $method;
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
     * Set the request parameter
     *
     * @param string $name
     * @param mixed $value
     *
     * @return Request
     * @throws Exception
     */
    public function setParam(string $name, $value): Request
    {
        if (is_object($value)) {
            if (property_exists($value, '__toString')) {
                $value = $value->__toString();
            } else {
                $class = get_class($value);
                throw new HttpException("Can not set param={$name} when instance of {$class} can't be converted to string");
            }
        }

        $this->params[$name] = $value;
        return $this;
    }

    /**
     * Set the parameters that will be sent. Any previously set parameters will be overridden.
     *
     * @param array $params
     *
     * @return Request
     */
    public function setParams(array $params): Request
    {
        $this->params = $params;
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
     * @return mixed|null
     */
    public function getParam($key)
    {
        return $this->hasParam($key) ? $this->params[$key] : null;
    }

    /**
     * Set the request header
     *
     * @param string $name
     * @param string $value
     *
     * @return Request
     */
    public function setHeader(string $name, string $value): Request
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Set the headers that will be sent. Any previously set headers will be overridden.
     *
     * @param array $headers
     *
     * @return Request
     */
    public function setHeaders(array $headers): Request
    {
        $this->headers = $headers;
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
     * @param string $name
     *
     * @return string|null
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
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
     * @param string $name
     *
     * @return Request
     */
    public function removeHeader(string $name): Request
    {
        if ($this->hasHeader($name)) {
            unset($this->headers[$name]);
        }
        return $this;
    }

    /**
     * Set the array of options. Array must be valid array with CURL constants as keys
     *
     * @param array $curlOptions
     *
     * @return Request
     * @link http://php.net/manual/en/function.curl-setopt.php
     */
    public function setOptions(array $curlOptions): Request
    {
        $this->options = $curlOptions;
        return $this;
    }

    /**
     * Set the CURL option
     *
     * @param string|int $name
     * @param mixed $value
     *
     * @return Request
     * @link http://php.net/manual/en/function.curl-setopt.php
     */
    public function setOption($name, $value): Request
    {
        $this->options[$name] = $value;
        return $this;
    }

    /**
     * Check if CURL option is set (exists in options array)
     *
     * @param string $key
     *
     * @return boolean
     */
    public function hasOption($key): bool
    {
        return isset($this->options[$key]);
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
     * @param string|int $option
     *
     * @return mixed|null
     */
    public function getOption($option)
    {
        return $this->options[$option] ?? null;
    }

    /**
     * @param int $option
     *
     * @return Request
     */
    public function removeOption($option): Request
    {
        if ($this->hasOption($option)) {
            unset($this->options[$option]);
        }

        return $this;
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
     * Get the prepared curl options for the HTTP request
     *
     * @return array
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
                if (!$this->hasOption(CURLOPT_CUSTOMREQUEST)) {
                    $options[CURLOPT_CUSTOMREQUEST] = $this->getMethod();
                }

                if (!$this->hasOption(CURLOPT_POSTFIELDS)) {
                    $options[CURLOPT_POSTFIELDS] = count($this->getParams()) > 0 ? http_build_query($this->getParams()) : '';
                }

                if ($this->hasHeader('Content-Type') && $this->getHeader('Content-Type') == 'application/json') {
                    $options[CURLOPT_POSTFIELDS] = Json::encode($this->getParams());
                }
                break;

            case self::PUT:
            case self::DELETE:
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
     * Execute request
     * @throws Exception
     * @return Response
     */
    public function exec(): Response
    {
        $url = $this->getUrl();

        $ch = curl_init($url);

        foreach ($this->getCurlOptions() as $option => $value) {
            // iterating it so we have all options applied in given order
            curl_setopt($ch, $option, $value);
        }

        $body = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new HttpException(curl_error($ch));
        }

        return new Response($ch, $body, $this);
    }

    /**
     * @param string $url
     * @param string $method
     * @param array|null $params
     * @param array|null $headers
     *
     * @return Response
     */
    protected static function quickRequest(string $url, string $method, array $params = null, array $headers = null): Response
    {
        /** @var Request $self */
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
     * Make quick GET request
     *
     * @param string $url
     * @param array|null $params
     * @param array|null $headers
     *
     * @return Response
     */
    public static function get(string $url, array $params = null, array $headers = null)
    {
        return static::quickRequest($url, self::GET, $params, $headers);
    }

    /**
     * Make quick POST request
     *
     * @param string $url
     * @param array|null $params
     * @param array|null $headers
     *
     * @return Response
     */
    public static function post(string $url, array $params = null, array $headers = null)
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
     */
    public static function put(string $url, array $params = null, array $headers = null)
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
     */
    public static function delete(string $url, array $params = null, array $headers = null)
    {
        return static::quickRequest($url, self::DELETE, $params, $headers);
    }

    /**
     * Print settings and all values useful for troubleshooting
     *
     * @return string
     */
    public function debug()
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

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->debug();
    }

}
