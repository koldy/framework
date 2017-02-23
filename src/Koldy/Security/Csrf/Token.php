<?php declare(strict_types = 1);

namespace Koldy\Security\Csrf;

use Koldy\Json;
use Koldy\Security\Exception as SecurityException;

/**
 * Class for holding down information about CSRF token
 */
class Token implements \Serializable
{

    /**
     * @var string
     */
    private $token;

    /**
     * @var string
     */
    private $cookieToken;

    /**
     * Token constructor.
     *
     * @param string $token
     * @param string $cookieToken
     */
    public function __construct(string $token, string $cookieToken)
    {
        $this->token = $token;
        $this->cookieToken = $cookieToken;
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @return string
     */
    public function getCookieToken(): string
    {
        return $this->cookieToken;
    }

    /**
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1.0
     */
    public function serialize()
    {
        return Json::encode([
          'token' => $this->getToken(),
          'cookie_token' => $this->getCookieToken()
        ]);
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
     * @throws SecurityException
     * @since 5.1.0
     */
    public function unserialize($serialized)
    {
        $data = Json::decode($serialized);

        foreach (['token', 'cookie_token'] as $key) {
            if (!isset($data[$key])) {
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
