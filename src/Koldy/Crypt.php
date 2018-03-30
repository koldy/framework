<?php declare(strict_types=1);

namespace Koldy;

use Koldy\Crypt\Exception as CryptException;
use Koldy\Crypt\MalformedException;

/**
 * This class provides some common crypt/decrypt features. Some security stuff relies on this class.
 *
 */
class Crypt
{

    protected const DEFAULT_METHOD = 'aes-256-cbc'; // will be used if it's not set in config

    /**
     * @return string
     * @throws Config\Exception
     * @throws Exception
     */
    final protected static function getMethod(): string
    {
        return Application::getConfig('application')->getArrayItem('security', 'openssl_default_method', self::DEFAULT_METHOD);
    }

    /**
     * Encrypt given texts. If method is not provided, default will be used
     *
     * @param string $plainText
     * @param string|null $key
     * @param string|null $method
     *
     * @return string
     * @throws Config\Exception
     * @throws CryptException
     * @throws Exception
     */
    final public static function encrypt(string $plainText, string $key = null, string $method = null): string
    {
        if ($method === null) {
            $method = static::getMethod();

            if (!in_array($method, openssl_get_cipher_methods())) {
                throw new CryptException("OpenSSL method={$method} defined in application config is not available");
            }
        } else {
            if (!in_array($method, openssl_get_cipher_methods())) {
                throw new CryptException("Passed OpenSSL method={$method} is not available");
            }
        }

        $key = Util::str2hex($key ?? Application::getKey());
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));

        $encrypted = @openssl_encrypt($plainText, $method, $key, 0, $iv);

        if ($encrypted === false) {
            throw new CryptException('Unable to encrypt your plain text; check your openssl_default_method in application config');
        }

        return $encrypted . ':' . bin2hex($iv);
    }

    /**
     * Decrypt encrypted string. If you provided key and/or method to encrypt, then you have to use the same values here as well.
     *
     * @param string $encryptedText
     * @param string|null $key
     * @param string|null $method
     *
     * @return string
     * @throws Config\Exception
     * @throws CryptException
     * @throws Exception
     * @throws MalformedException
     */
    final public static function decrypt(string $encryptedText, string $key = null, string $method = null): string
    {
        if ($method === null) {
            $method = static::getMethod();

            if (!in_array($method, openssl_get_cipher_methods())) {
                throw new CryptException("OpenSSL method={$method} defined in application config is not available");
            }
        } else {
            if (!in_array($method, openssl_get_cipher_methods())) {
                throw new CryptException("Passed OpenSSL method={$method} is not available");
            }
        }

        $text = explode(':', $encryptedText);

        if (count($text) == 1) {
            throw new CryptException('Invalid encrypted text provided');
        }

        $key = Util::str2hex($key ?? Application::getKey());
        $iv = hex2bin($text[1]);

        $decrypted = @openssl_decrypt($text[0], $method, $key, 0, $iv);

        if ($decrypted === false || !ctype_print($decrypted)) {
            throw new MalformedException('Decrypted string contains non-printable characters, which are probably caused by corrupted encrypted text');
        }

        return $decrypted;
    }

}
