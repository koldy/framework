<?php declare(strict_types=1);

namespace Koldy\Request;

use Koldy\Convert;
use Koldy\Security\Exception as SecurityException;

/**
 * @phpstan-consistent-constructor
 */
class UploadedFile
{

	protected string $name;

	protected string $mimeType;

	/**
	 * Mime type detected by mime_content_type()
	 *
	 * @var string|null
	 */
	protected string|null $detectedMimeType = null;

	protected int $size;

	protected string $tmpName;

	protected int $errorCode;

	protected string|null $location = null;

	/**
	 * UploadedFile constructor.
	 *
	 * @param string $name
	 * @param string $mimeType
	 * @param int $size
	 * @param string $tmpName
	 * @param int $errorCode
	 *
	 * @throws SecurityException
	 */
	public function __construct(string $name, string $mimeType, int $size, string $tmpName, int $errorCode)
	{
		$this->name = $name;
		$this->mimeType = $mimeType;
		$this->size = $size;
		$this->tmpName = $tmpName;
		$this->errorCode = $errorCode;

		if (!$this->hasError() && !is_uploaded_file($tmpName)) {
			throw new SecurityException("Given {$tmpName} is not valid uploaded file");
		}
	}

	/**
	 * Returns true if the uploaded file came with an error
	 *
	 * @return bool
	 */
	public function hasError(): bool
	{
		return $this->errorCode !== UPLOAD_ERR_OK;
	}

	/**
	 * Creates new instance of UploadedFile from a "single" file array
	 *
	 * @param string $name the name of a key in $_FILES array from which you want to create new instance of UploadedFile
	 *
	 * @return static|null
	 * @throws SecurityException
	 */
	public static function createFromFilesArray(string $name): ?static
	{
		/** @phpstan-ignore isset.variable */
		if (!isset($_FILES) || !array_key_exists($name, $_FILES)) {
			return null;
		}

		['name' => $name, 'type' => $type, 'tmp_name' => $tmpName, 'error' => $error, 'size' => $size] = $_FILES[$name];

		return new static($name, $type, $size, $tmpName, $error);
	}

	/**
	 * Get the file's extension, lowercase. If extension can't be detected (for example, files that has no dot in name),
	 * you'll get null back.
	 *
	 * @return string|null
	 */
	public function getExtension(): ?string
	{
		$pos = strrpos($this->getName(), '.');

		if ($pos === false) {
			return null;
		}

		return strtolower(substr($this->getName(), $pos + 1));
	}

	/**
	 * Get file's original name
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Get the size of file as string, so the size od 1024 would be 1 KB
	 *
	 * @return string
	 */
	public function getSizeString(): string
	{
		return Convert::bytesToString($this->getSize());
	}

	/**
	 * Get the size of file in bytes as integer
	 *
	 * @return int
	 */
	public function getSize(): int
	{
		return $this->size;
	}

	/**
	 * Get the error code of uploaded file (the "error" information from $_FILES array) - this method will return null
	 * if there's no upload error.
	 *
	 * @return int|null
	 */
	public function getErrorCode(): ?int
	{
		if ($this->errorCode === UPLOAD_ERR_OK) {
			return null;
		}

		return $this->errorCode;
	}

	/**
	 * Get the current location of file on file system. If it wasn't moved, path will point to tmp folder. Otherwise,
	 * you'll get the last location provided through move() method.
	 *
	 * @return string|null
	 */
	public function getLocation(): ?string
	{
		return $this->location ?? $this->tmpName;
	}

	/**
	 * Get the PHP's error code; returns null if there's no error
	 *
	 * @return int|null
	 */
	public function getError(): ?int
	{
		if ($this->errorCode === UPLOAD_ERR_OK) {
			return null;
		}

		return $this->errorCode;
	}

	/**
	 * Gets the error description as string, as described on official PHP documentation. If there's no error, null will
	 * be returned.
	 *
	 * @return string|null
	 *
	 * @throws Exception
	 * @link https://www.php.net/manual/en/features.file-upload.errors.php
	 */
	public function getErrorDescription(): ?string
	{
		switch ($this->errorCode) {
			case UPLOAD_ERR_OK:
				return null;

			case UPLOAD_ERR_INI_SIZE:
				return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';

			case UPLOAD_ERR_FORM_SIZE:
				return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.';

			case UPLOAD_ERR_PARTIAL:
				return 'The uploaded file was only partially uploaded.';

			case UPLOAD_ERR_NO_FILE:
				return 'No file was uploaded.';

			case UPLOAD_ERR_NO_TMP_DIR:
				return 'Missing a temporary folder.';

			case UPLOAD_ERR_CANT_WRITE:
				return 'Failed to write file to disk.';

			case UPLOAD_ERR_EXTENSION:
				$loadedExtensions = get_loaded_extensions();
				return 'A PHP extension stopped the file upload. PHP does not provide a way to ascertain which extension caused the file upload to stop so you have to examine loaded extensions yourself. Currently loaded extensions are: ' . implode(', ',
						$loadedExtensions);

			default:
				throw new Exception("Unknown upload error code: {$this->errorCode}");
		}
	}

	/**
	 * Moves file to given location. Full file system path must be specified.
	 *
	 * @param string $to
	 *
	 * @throws Exception
	 */
	public function move(string $to): void
	{
		if ($this->location === null) {
			// first call should be done with "move_uploaded_file", and all other will be done with rename()
			if (move_uploaded_file($this->tmpName, $to) === false) {
				throw new Exception("Unable to move uploaded file {$this->name} from {$this->tmpName} to {$to}");
			}
		} else {
			// every other move should be done with rename()
			if (rename($this->location, $to) === false) {
				throw new Exception("Unable to move uploaded file {$this->name} from {$this->location} to {$to}");
			}
		}

		$this->location = $to;
	}

	/**
	 * Reads the file and checks if file's mime type starts with image
	 *
	 * @return bool
	 */
	public function isImage(): bool
	{
		return str_starts_with($this->getMimeType(), 'image');
	}

	/**
	 * Get detected mime type by looking into file. If option for checking it is not available, then standard mimeType
	 * from $_FILES will be returned.
	 *
	 * @return string
	 */
	public function getMimeType(): string
	{
		if ($this->detectedMimeType !== null) {
			return $this->detectedMimeType;
		}

		if (function_exists('mime_content_type')) {
			if (($mimeType = mime_content_type($this->location ?? $this->tmpName)) !== false) {
				$this->detectedMimeType = $mimeType;
			} else {
				// unable to detect the real mime type based on content
				$this->detectedMimeType = $this->mimeType;
			}
		} else {
			$this->detectedMimeType = $this->mimeType;
		}

		return $this->detectedMimeType;
	}

}
