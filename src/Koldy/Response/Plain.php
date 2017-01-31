<?php declare(strict_types = 1);

namespace Koldy\Response;

/**
 * Class for printing plain text as response to HTTP request.
 *
 * @link http://koldy.net/docs/plain
 */
class Plain extends AbstractResponse
{

    /**
     * This is data holder for this class
     *
     * @var string
     */
    private $content = null;

    public function __construct(string $content = '')
    {
        $this->content = $content;
        $this->setHeader('Content-Type', 'text/plain');
    }

    /**
     * Create the instance statically
     *
     * @param string $text
     *
     * @return Plain
     */
    public static function create(string $text = ''): Plain
    {
        return new static($text);
    }

    /**
     * Set the text in this object
     *
     * @param string $content
     *
     * @return Plain
     */
    public function setContent(string $content): Plain
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Get the text stored in this object
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Append current text with given text
     *
     * @param string $content
     *
     * @return Plain
     */
    public function append(string $content): Plain
    {
        $this->content .= $content;
        return $this;
    }

    /**
     * Prepend current text with given text
     *
     * @param string $content
     *
     * @return Plain
     */
    public function prepend(string $content): Plain
    {
        $this->content = "{$content}{$this->content}";
        return $this;
    }

    /**
     * @link http://koldy.net/docs/plain#usage
     */
    public function flush(): void
    {
        $this->prepareFlush();
        $this->runBeforeFlush();

        ob_start();

        // print the text stored in this object
        print $this->content;

        $size = ob_get_length();
        $this->setHeader('Content-Length', $size);

        $this->flushHeaders();

        ob_end_flush();
        flush();

        $this->runAfterFlush();
    }

}
