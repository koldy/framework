<?php declare(strict_types=1);

namespace Koldy\Mail\Adapter;

use InvalidArgumentException;

/**
 * Class CommonMailAdapter
 * @package Koldy\Mail\Adapter
 */
abstract class CommonMailAdapter extends AbstractMailAdapter
{

	/**
	 * The array of recipients
	 *
	 * @var array
	 */
	protected array $to = [];

	/**
	 * The array of CC recipients
	 *
	 * @var array
	 */
	protected array $cc = [];

	/**
	 * The array of BCC recipients
	 *
	 * @var array
	 */
	protected array $bcc = [];

	/**
	 * @var string|null
	 */
	protected string|null $fromEmail = null;

	/**
	 * @var string|null
	 */
	protected string|null $fromName = null;

	/**
	 * @var string|null
	 */
	protected string|null $replyTo = null;

	/**
	 * @var string|null
	 */
	protected string|null $subject = null;

	/**
	 * @var string|null
	 */
	protected string|null $body = null;

	/**
	 * @var string|null
	 */
	protected string|null $alternativeText = null;

	/**
	 * @var bool
	 */
	protected bool $isHTML = false;

	/**
	 * @var array
	 */
	protected array $attachedFiles = [];

	/**
	 * Set email's "from"
	 *
	 * @param string $email
	 * @param string|null $name [optional]
	 *
	 * @return static
	 */
	public function from(string $email, string|null $name = null): CommonMailAdapter
	{
		$this->fromEmail = $email;
		$this->fromName = $name;

		return $this;
	}

	/**
	 * Set email's "Reply To" option
	 *
	 * @param string $email
	 * @param string|null $name [optional]
	 *
	 * @return static
	 */
	public function replyTo(string $email, string|null $name = null): CommonMailAdapter
	{
		$this->replyTo = $this->getAddressValue($email, $name);
		return $this;
	}

	/**
	 * Set email's "to"
	 *
	 * @param string $email
	 * @param string|null $name [optional]
	 *
	 * @return static
	 */
	public function to(string $email, string|null $name = null): CommonMailAdapter
	{
		$this->to[] = [
			'email' => $email,
			'name' => $name
		];

		return $this;
	}

	/**
	 * Set email's "cc"
	 *
	 * @param string $email
	 * @param string|null $name [optional]
	 *
	 * @return static
	 */
	public function cc(string $email, string|null $name = null): CommonMailAdapter
	{
		$this->cc[] = [
			'email' => $email,
			'name' => $name
		];

		return $this;
	}

	/**
	 * Set email's "bcc"
	 *
	 * @param string $email
	 * @param string|null $name [optional]
	 *
	 * @return static
	 */
	public function bcc(string $email, string|null $name = null): CommonMailAdapter
	{
		$this->bcc[] = [
			'email' => $email,
			'name' => $name
		];

		return $this;
	}

	/**
	 * Set email's subject
	 *
	 * @param string $subject
	 *
	 * @return static
	 */
	public function subject(string $subject): CommonMailAdapter
	{
		$this->subject = $subject;
		return $this;
	}

	/**
	 * Sets the e-mail's body in HTML format. If you want to send plain text only, please use plain() method.
	 *
	 * @param string $body
	 * @param boolean $isHTML
	 * @param string|null $alternativeText
	 *
	 * @return static
	 */
	public function body(string $body, bool $isHTML = false, string|null $alternativeText = null): CommonMailAdapter
	{
		$this->body = $body;
		$this->isHTML = $isHTML;
		$this->alternativeText = $alternativeText;
		return $this;
	}

	/**
	 * Attach file to e-mail
	 *
	 * @param string $fullFilePath
	 * @param string|null $attachedAsName [optional]
	 *
	 * @return static
	 */
	public function attachFile(string $fullFilePath, string|null $attachedAsName = null): CommonMailAdapter
	{
		if (strlen($fullFilePath) == 0) {
			throw new InvalidArgumentException('Attached file path can not be empty string');
		}

		if ($attachedAsName === null) {
			$attachedAsName = basename($fullFilePath);
		}

		$fileName = basename($fullFilePath);

		if (!str_contains($fileName, '.')) {
			throw new InvalidArgumentException("Unable to attach file from path={$fullFilePath} because file extension can't be detected");
		}

		$extension = substr($fileName, strrpos($fileName, '.') + 1);

		$this->attachedFiles[] = [
			'fullFilePath' => $fullFilePath,
			'fileName' => $fileName,
			'extension' => $extension,
			'attachedAsName' => $attachedAsName
		];

		return $this;
	}

}
