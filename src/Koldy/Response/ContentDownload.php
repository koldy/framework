<?php declare(strict_types=1);

namespace App;

use Koldy\Response\AbstractResponse;
use Koldy\Response\Exception;

/**
 * Force content download to output buffer, so it'll be downloaded same as file.
 *
 */
class ContentDownload extends AbstractResponse
{

    /**
     * This variable will contain content you want to send to user
     *
     * @var string
     */
    protected $content = null;

    /**
     * Download as name (file name that user will get)
     *
     * @var string
     */
    protected $asName = null;

    /**
     * The content type of download
     *
     * @var string
     */
    protected $contentType = null;

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
     * @return ContentDownload
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
     * @return ContentDownload
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
     * @param string $contentType [optional]
     *
     * @return ContentDownload
     */
    public static function create(string $content, string $asName, string $contentType = null): ContentDownload
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
     */
    public function flush(): void
    {
        $asName = $this->asName;
        if ($asName === null) {
            throw new Exception('Can not download content because "as name" is not set. Please use "setAsName()" method before flushing.');
        }

        $this->prepareFlush();
        $this->runBeforeFlush();
        $contentType = null;

        if ($this->contentType === null) {
            $contentType = 'application/octet-stream';
        } else {
            $contentType = $this->contentType;
        }

        if ($contentType === null) {
            $contentType = 'application/force-download';
        }

        $this->setHeader('Connection', 'close')
            ->setHeader('Pragma', 'public')
            ->setHeader('Expires', 0)
            ->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
            ->setHeader('Cache-Control', 'public')
            ->setHeader('Content-Description', 'File Transfer')
            ->setHeader('Content-Length', mb_strlen($this->content))
            ->setHeader('Content-Type', $contentType)
            ->setHeader('Content-Disposition', "attachment; filename=\"{$asName}\";")
            ->setHeader('Content-Transfer-Encoding', 'binary');

        set_time_limit(0);
        $this->flushHeaders();

        print($this->content);

        @ob_flush();
        flush();

        $this->runAfterFlush();
    }

}
