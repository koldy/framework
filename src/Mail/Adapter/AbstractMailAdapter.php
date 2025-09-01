<?php namespace Koldy\Mail\Adapter;

use Koldy\Mail\Exception;

/**
 * If you want to create your own adapter for sending e-mails, then please
 * extend this class. Thank you!
 *
 */
abstract class AbstractMailAdapter
{

	/**
	 * The config array passed from config/mail.php; the 'options' key
	 *
	 * @var array
	 */
	protected array $config;

	/**
	 * Registered headers
	 * @var array
	 */
	private array $headers = [];

	/**
	 * Construct the object with configuration array from config/mail.php, from 'options' key
	 *
	 * @param array $config
	 */
	public function __construct(array $config)
	{
		$this->config = $config;
	}

	/**
	 * Set From
	 *
	 * @param string $email
	 * @param string|null $name [optional]
	 *
	 * @return AbstractMailAdapter
	 * @link http://koldy.net/docs/mail#example
	 */
	abstract public function from(string $email, string|null $name = null): AbstractMailAdapter;

	/**
	 * Set "Reply-To"
	 *
	 * @param string $email
	 * @param string|null $name [optional]
	 *
	 * @return static
	 * @link http://koldy.net/docs/mail#example
	 */
	abstract public function replyTo(string $email, string|null $name = null): AbstractMailAdapter;

	/**
	 * Send mail to this e-mail
	 *
	 * @param string $email
	 * @param string|null $name [optional]
	 *
	 * @return static
	 * @link http://koldy.net/docs/mail#example
	 */
	abstract public function to(string $email, string|null $name = null): AbstractMailAdapter;

	/**
	 * Send mail carbon copy
	 *
	 * @param string $email
	 * @param string|null $name [optional]
	 *
	 * @return static
	 * @link http://koldy.net/docs/mail#example
	 */
	abstract public function cc(string $email, string|null $name = null): AbstractMailAdapter;

	/**
	 * Send mail blind carbon copy
	 *
	 * @param string $email
	 * @param string|null $name [optional]
	 *
	 * @return static
	 * @link http://koldy.net/docs/mail#example
	 */
	abstract public function bcc(string $email, string|null $name = null): AbstractMailAdapter;

	/**
	 * Set the e-mail subject
	 *
	 * @param string $subject
	 *
	 * @return static
	 * @link http://koldy.net/docs/mail#example
	 */
	abstract public function subject(string $subject): AbstractMailAdapter;

	/**
	 * Set e-mail body
	 *
	 * @param string $body
	 * @param boolean $isHTML
	 * @param string|null $alternativeText The plain text
	 *
	 * @return static
	 * @link http://koldy.net/docs/mail#example
	 */
	abstract public function body(
		string $body,
		bool $isHTML = false,
		string|null $alternativeText = null
	): AbstractMailAdapter;

	/**
	 * Attach file into this e-mail
	 *
	 * @param string $fullFilePath
	 * @param string|null $attachedAsName
	 *
	 * @return static
	 * @link http://koldy.net/docs/mail#header-and-files
	 */
	abstract public function attachFile(string $fullFilePath, string|null $attachedAsName = null): AbstractMailAdapter;

	/**
	 * Actually sends an e-mail
	 *
	 * @throws Exception
	 * @link http://koldy.net/docs/mail#example
	 */
	abstract public function send(): void;

	/**
	 * Set the custom/additional mail header
	 *
	 * @param string $name
	 * @param string $value
	 *
	 * @return static
	 * @link http://koldy.net/docs/mail#header-and-files
	 */
	public function setHeader(string $name, string $value): AbstractMailAdapter
	{
		$this->headers[$name] = $value;
		return $this;
	}

	/**
	 * Is there a header with given name?
	 *
	 * @param string $name
	 *
	 * @return bool
	 */
	public function hasHeader(string $name): bool
	{
		return array_key_exists($name, $this->headers);
	}

	/**
	 * Remove the header
	 *
	 * @param string $name
	 *
	 * @return static
	 */
	public function removeHeader(string $name): AbstractMailAdapter
	{
		if (array_key_exists($name, $this->headers)) {
			unset($this->headers[$name]);
		}

		return $this;
	}

	/**
	 * Get the header's value
	 *
	 * @param string $name
	 *
	 * @return string|null
	 */
	public function getHeader(string $name): ?string
	{
		return $this->headers[$name] ?? null;
	}

	/**
	 * Get all headers in key => value format
	 * @return array
	 */
	protected function getHeaders(): array
	{
		return $this->headers;
	}

	/**
	 * Get the headers list with items as "key: value"
	 * @return string[]
	 */
	protected function getHeadersList(): array
	{
		$headers = [];

		foreach ($this->headers as $key => $value) {
			$headers[] = "{$key}: {$value}";
		}

		return $headers;
	}

	/**
	 * Internal helper to get the proper address header value
	 *
	 * @param string $email
	 * @param string|null $name
	 *
	 * @return string
	 */
	protected function getAddressValue(string $email, string|null $name = null): string
	{
		if ($name === null || $name === '') {
			return $email;
		} else {
			return "{$name} <{$email}>";
		}
	}

}
