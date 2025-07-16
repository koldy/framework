<?php declare(strict_types=1);

namespace Koldy\Response;

/**
 * Force content download to output buffer, so it'll be downloaded same as file.
 * @phpstan-consistent-constructor
 */
class ContentDownload extends AbstractResponse
{

    /**
     * This variable will contain content you want to send to user
     *
     * @var string
     */
    protected string $content;

    /**
     * Download as name (file name that user will get)
     *
     * @var string|null
     */
    protected string | null $asName = null;

    /**
     * The content type of download
     *
     * @var string|null
     */
    protected string | null $contentType = null;

    /**
     * ContentDownload constructor.
     *
     * @param string $content
     */
    public function __construct(string $content)
    {
        $this->content = $content;
    }

    /**
     * Set custom name under which you want to send file as "download"
     *
     * @param string $asName
     *
     * @return static
     */
    public function setAsName(string $asName): ContentDownload
    {
        $this->asName = $asName;
        return $this;
    }

    /**
     * Set the content type under which you want to serve the file
     *
     * @param string $contentType
     *
     * @return static
     */
    public function setContentType(string $contentType): ContentDownload
    {
        $this->contentType = $contentType;
        return $this;
    }

	/**
	 * Shorthand for creating this class, pass all required parameters at once
	 *
	 * @param string $content
	 * @param string $asName
	 * @param string|null $contentType [optional]
	 *
	 * @return static
	 */
    public static function create(string $content, string $asName, string|null $contentType = null): ContentDownload
    {
        $self = new static($content);
        $self->setAsName($asName);

        if ($contentType !== null) {
            $self->setContentType($contentType);
        }

        return $self;
    }

	/**
	 * @throws Exception
	 * @throws \Koldy\Exception
	 */
    public function flush(): void
    {
        $asName = $this->asName;
        if ($asName === null) {
            throw new Exception('Can not download content because "as name" is not set. Please use "setAsName()" method before flushing.');
        }

        $this->prepareFlush();
        $this->runBeforeFlush();

        if ($this->contentType === null) {
            $contentType = 'application/octet-stream';
        } else {
            $contentType = $this->contentType;
        }

        $this
	        ->setHeader('Connection', 'close')
            ->setHeader('Pragma', 'public')
            ->setHeader('Expires', 0)
            ->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
            ->setHeader('Cache-Control', 'public')
            ->setHeader('Content-Description', 'File Transfer')
            ->setHeader('Content-Type', $contentType)
            ->setHeader('Content-Disposition', "attachment; filename=\"{$asName}\";")
            ->setHeader('Content-Transfer-Encoding', 'binary');

        set_time_limit(0);
        $this->flushHeaders();

	    @ob_start();
        file_put_contents('php://output', $this->content);
        @ob_flush();

        flush();

        $this->runAfterFlush();
    }

}
