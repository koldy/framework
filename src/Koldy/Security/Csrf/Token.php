<?php declare(strict_types=1);

namespace Koldy\Security\Csrf;

use Koldy\Json;
use Koldy\Security\Exception as SecurityException;

/**
 * Class for holding down information about CSRF token
 */
class Token
{

    /**
     * @var string
     */
    private string $token;

    /**
     * @var string|null
     */
    private string | null $cookieToken = null;

    /**
     * Token constructor.
     *
     * @param string $token
     * @param string|null $cookieToken
     */
    public function __construct(string $token, string $cookieToken = null)
    {
        $this->token = $token;
        $this->cookieToken = $cookieToken;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getCookieToken(): ?string
    {
        return $this->cookieToken;
    }

    /**
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1.0
     * @throws Json\Exception
     */
    public function serialize()
    {
        return Json::encode([
          'token' => $this->getToken(),
          'cookie_token' => $this->getCookieToken()
        ]);
    }

	/**
	 * Serialize handler for newer PHP versions
	 */
	public function __serialize()
	{
		return [
			'token' => $this->getToken(),
			'cookie_token' => $this->getCookieToken()
		];
	}

    /**
     * Constructs the object
     * @link http://php.net/manual/en/serializable.unserialize.php
     *
     * @param string $serialized <p>
     * The string representation of the object.
     * </p>
     *
     * @return void
     * @throws Json\Exception
     * @throws SecurityException
     * @since 5.1.0
     */
    public function unserialize($serialized)
    {
        $data = Json::decode($serialized);

        foreach (['token', 'cookie_token'] as $key) {
            if (!array_key_exists($key, $data)) {
                throw new SecurityException("Unserialized CSRF token doesn't contain required \"{$key}\" key");
            }
        }

        $this->token = $data['token'];
        $this->cookieToken = $data['cookie_token'];
    }

	/**
	 * Unserialize handler for newer PHP versions
	 *
	 * @param array $data
	 *
	 * @throws SecurityException
	 */
	public function __unserialize(array $data): void
	{
		foreach (['token', 'cookie_token'] as $key) {
			if (!array_key_exists($key, $data)) {
				throw new SecurityException("Unserialized CSRF token doesn't contain required \"{$key}\" key");
			}
		}

		$this->token = $data['token'];
		$this->cookieToken = $data['cookie_token'];
	}

    public function __toString()
    {
        return $this->getToken();
    }

}
