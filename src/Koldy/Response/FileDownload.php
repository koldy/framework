<?php declare(strict_types=1);

namespace Koldy\Response;

use Koldy\Filesystem\File;
use Koldy\Http\Mime;
use Koldy\Response\Exception as ResponseException;

/**
 * Force file download to output buffer.
 *
 */
class FileDownload extends AbstractResponse
{

    /**
     * This variable will contain path to file you want to download
     *
     * @var File
     */
    protected $file = null;

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
     * FileDownload constructor.
     *
     * @param File $file
     */
    public function __construct(File $file)
    {
        $this->file = $file;
    }

    /**
     * Set custom name under which you want to send file as "download"
     *
     * @param string $asName
     *
     * @return FileDownload
     */
    public function setAsName(string $asName): FileDownload
    {
        $this->asName = $asName;
        return $this;
    }

    /**
     * Set the content type under which you want to serve the file
     *
     * @param string $contentType
     *
     * @return FileDownload
     */
    public function setContentType(string $contentType): FileDownload
    {
        $this->contentType = $contentType;
        return $this;
    }

    /**
     * Return file download
     *
     * @param string $path
     * @param string $asName [optional]
     * @param string $contentType [optional]
     *
     * @throws Exception
     * @return FileDownload
     */
    public static function create(string $path, string $asName = null, string $contentType = null): FileDownload
    {
        $self = new static(new File($path));

        if ($asName !== null) {
            $self->setAsName($asName);
        }

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
        // it is file download!
        if (!$this->file->isReadable()) {
            throw new ResponseException("Can not start file download of file on path={$this->file->getRealPath()}");
        }

        $this->runBeforeFlush();
        $contentType = null;

        if ($this->contentType === null) {
            $extension = strtolower($this->file->getExtension());

            if (strlen($extension) > 0) {
                $contentType = Mime::getMimeByExtension($extension);
            }
        } else {
            $contentType = $this->contentType;
        }

        if ($contentType === null) {
            $contentType = 'application/force-download';
        }

        $asName = $this->asName ?? basename($this->file->getFilename());

        $this->setHeader('Connection', 'close')
          ->setHeader('Pragma', 'public')
          ->setHeader('Expires', 0)
          ->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
          ->setHeader('Cache-Control', 'public')
          ->setHeader('Content-Description', 'File Transfer')
          ->setHeader('Content-Length', $this->file->getSize())
          ->setHeader('Content-Type', $contentType)
          ->setHeader('Content-Disposition', "attachment; filename=\"{$asName}\";")
          ->setHeader('Content-Transfer-Encoding', 'binary');

        set_time_limit(0);
        $this->flushHeaders();

        $file = @fopen($this->file->getRealPath(), 'rb');
        while (!feof($file)) {
            print(@fread($file, 8192)); // download in chunks per 8kb
            flush();
        }

        @fclose($file);
        @ob_flush();
        flush();

        $this->runAfterFlush();
    }

}
