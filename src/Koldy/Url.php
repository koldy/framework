<?php declare(strict_types = 1);

namespace Koldy;

use Koldy\Url\Exception as UrlException;

/**
 * Class Url - Helper for working with URL-s
 * @package Koldy
 */
class Url
{

    /**
     * @var string
     */
    private $url;

    /**
     * @var array
     */
    protected $segments;

    /**
     * Url constructor.
     *
     * @param string $url
     *
     * @throws Exception
     */
    public function __construct(string $url)
    {
        if (($this->segments = parse_url($url)) === false) {
            throw new UrlException('Unable to parse URL: ' . $url);
        }

        $this->url = $url;
    }

    /**
     * Get URL's scheme
     *
     * @return string
     * @throws Exception
     */
    public function getScheme(): string
    {
        if (!isset($this->segments['scheme'])) {
            throw new UrlException('Unable to get scheme for ' . $this->url);
        }

        return $this->segments['scheme'];
    }

    /**
     * Get URL's host
     *
     * @return string
     * @throws Exception
     */
    public function getHost(): string
    {
        if (!isset($this->segments['host'])) {
            throw new UrlException('Unable to get host for ' . $this->url);
        }

        return $this->segments['host'];
    }

    /**
     * Get URL's port
     *
     * @return int
     * @throws Exception
     */
    public function getPort(): int
    {
        if (!isset($this->segments['port'])) {
            throw new UrlException('Unable to get port for ' . $this->url);
        }

        return (int)$this->segments['port'];
    }

    /**
     * Get URL's user
     *
     * @return string
     * @throws Exception
     */
    public function getUser(): string
    {
        if (!isset($this->segments['user'])) {
            throw new UrlException('Unable to get user for ' . $this->url);
        }

        return $this->segments['user'];
    }

    /**
     * Get URL's pass
     *
     * @return string
     * @throws Exception
     */
    public function getPass(): string
    {
        if (!isset($this->segments['pass'])) {
            throw new UrlException('Unable to get pass for ' . $this->url);
        }

        return $this->segments['pass'];
    }

    /**
     * Get URL's path (part after domain, also known as URI)
     *
     * @return string
     * @throws Exception
     */
    public function getPath(): string
    {
        if (!isset($this->segments['path'])) {
            throw new UrlException('Unable to get path for ' . $this->url);
        }

        return $this->segments['path'];
    }

    /**
     * Get URL's query (part after question mark (?))
     *
     * @return string
     * @throws Exception
     */
    public function getQuery(): string
    {
        if (!isset($this->segments['query'])) {
            throw new UrlException('Unable to get query for ' . $this->url);
        }

        return $this->segments['query'];
    }

    /**
     * Get URL's fragment (part after the hash (#))
     *
     * @return string
     * @throws Exception
     */
    public function getFragment(): string
    {
        if (!isset($this->segments['fragment'])) {
            throw new UrlException('Unable to get fragment for ' . $this->url);
        }

        return $this->segments['fragment'];
    }

    public function __toString(): string
    {
        return $this->url;
    }

}
