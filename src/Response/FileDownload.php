<?php declare(strict_types=1);

namespace Koldy\Response;

use Koldy\Filesystem\File;
use Koldy\Http\Mime;
use Koldy\Response\Exception as ResponseException;

/**
 * Force file download to output buffer.
 * @phpstan-consistent-constructor
 */
class FileDownload extends AbstractResponse
{

	/**
	 * This variable will contain path to file you want to download
	 *
	 * @var File
	 */
	protected File $file;

	/**
	 * Download as name (file name that user will get)
	 *
	 * @var string|null
	 */
	protected string|null $asName = null;

	/**
	 * The content type of download
	 *
	 * @var string|null
	 */
	protected string|null $contentType = null;

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
	 * Return file download
	 *
	 * @param string $path
	 * @param string|null $asName [optional]
	 * @param string|null $contentType [optional]
	 *
	 * @return static
	 */
	public static function create(
		string $path,
		string|null $asName = null,
		string|null $contentType = null
	): FileDownload {
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
	 * Set custom name under which you want to send file as "download"
	 *
	 * @param string $asName
	 *
	 * @return static
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
	 * @return static
	 */
	public function setContentType(string $contentType): FileDownload
	{
		$this->contentType = $contentType;
		return $this;
	}

	public function getOutput(): mixed
	{
		// when having a content download (which is basically a file), then we won't "remember" the output because it
		// can be potentially huge, so remembering an output could become a memory problem
		return null;
	}

	/**
	 * @throws Exception
	 * @throws \Koldy\Exception
	 * @throws \Koldy\Http\Exception
	 */
	public function flush(): void
	{
		// it is file download!
		if (!$this->file->isReadable()) {
			throw new ResponseException("Can not start file download of file on path={$this->file->getRealPath()}");
		}

		$this->prepareFlush();
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
		flush();

		$this->runAfterFlush();
	}

}
