<?php

declare(strict_types=1);

namespace Tests;

use Koldy\Crypt;
use Koldy\Crypt\Exception as CryptException;
use Koldy\Crypt\MalformedException;
use PHPUnit\Framework\TestCase;

class CryptTest extends TestCase
{

	private const KEY = 'TestSecretKey1234';
	private const METHOD = 'aes-256-cbc';

	// ── encrypt ──

	public function testEncryptReturnsNonEmptyString(): void
	{
		$encrypted = Crypt::encrypt('Hello World', self::KEY, self::METHOD);
		$this->assertNotEmpty($encrypted);
	}

	public function testEncryptContainsColonSeparator(): void
	{
		$encrypted = Crypt::encrypt('Hello World', self::KEY, self::METHOD);
		$this->assertStringContainsString(':', $encrypted);
	}

	public function testEncryptProducesDifferentOutputEachTime(): void
	{
		$a = Crypt::encrypt('Hello World', self::KEY, self::METHOD);
		$b = Crypt::encrypt('Hello World', self::KEY, self::METHOD);
		// Due to random IV, same plaintext should produce different ciphertext
		$this->assertNotSame($a, $b);
	}

	public function testEncryptEmptyString(): void
	{
		$encrypted = Crypt::encrypt('', self::KEY, self::METHOD);
		$this->assertNotEmpty($encrypted);
		$this->assertStringContainsString(':', $encrypted);
	}

	public function testEncryptInvalidMethodThrows(): void
	{
		$this->expectException(CryptException::class);
		$this->expectExceptionMessage('is not available');
		Crypt::encrypt('Hello', self::KEY, 'non-existent-cipher-method');
	}

	// ── decrypt ──

	public function testDecryptRoundtrip(): void
	{
		$plainText = 'Hello World';
		$encrypted = Crypt::encrypt($plainText, self::KEY, self::METHOD);
		$decrypted = Crypt::decrypt($encrypted, self::KEY, self::METHOD);
		$this->assertSame($plainText, $decrypted);
	}

	public function testDecryptRoundtripSpecialCharacters(): void
	{
		$plainText = 'Special chars: !@#$%^&*()_+-=[]{}|;:,.<>?';
		$encrypted = Crypt::encrypt($plainText, self::KEY, self::METHOD);
		$decrypted = Crypt::decrypt($encrypted, self::KEY, self::METHOD);
		$this->assertSame($plainText, $decrypted);
	}

	public function testDecryptRoundtripLongString(): void
	{
		$plainText = str_repeat('abcdefghij', 100);
		$encrypted = Crypt::encrypt($plainText, self::KEY, self::METHOD);
		$decrypted = Crypt::decrypt($encrypted, self::KEY, self::METHOD);
		$this->assertSame($plainText, $decrypted);
	}

	public function testDecryptRoundtripWithDifferentKeys(): void
	{
		$plainText = 'Secret message';
		$key1 = 'KeyOne1234567890';
		$key2 = 'KeyTwo1234567890';

		$encrypted = Crypt::encrypt($plainText, $key1, self::METHOD);

		$this->expectException(MalformedException::class);
		Crypt::decrypt($encrypted, $key2, self::METHOD);
	}

	public function testDecryptInvalidEncryptedTextThrows(): void
	{
		$this->expectException(CryptException::class);
		$this->expectExceptionMessage('Invalid encrypted text provided');
		Crypt::decrypt('no-colon-here', self::KEY, self::METHOD);
	}

	public function testDecryptInvalidMethodThrows(): void
	{
		$this->expectException(CryptException::class);
		$this->expectExceptionMessage('is not available');
		Crypt::decrypt('abc:def', self::KEY, 'non-existent-cipher-method');
	}

	public function testDecryptCorruptedCiphertextThrows(): void
	{
		$this->expectException(MalformedException::class);
		// Valid format (has colon) but corrupted data
		$fakeIv = bin2hex(openssl_random_pseudo_bytes(16));
		Crypt::decrypt('corrupted_base64_data:' . $fakeIv, self::KEY, self::METHOD);
	}

	// ── different cipher methods ──

	public function testRoundtripWithAes128Cbc(): void
	{
		$method = 'aes-128-cbc';
		if (!in_array($method, openssl_get_cipher_methods())) {
			$this->markTestSkipped("Cipher method {$method} not available");
		}

		$plainText = 'Testing AES-128-CBC';
		$encrypted = Crypt::encrypt($plainText, self::KEY, $method);
		$decrypted = Crypt::decrypt($encrypted, self::KEY, $method);
		$this->assertSame($plainText, $decrypted);
	}

	public function testRoundtripWithAes192Cbc(): void
	{
		$method = 'aes-192-cbc';
		if (!in_array($method, openssl_get_cipher_methods())) {
			$this->markTestSkipped("Cipher method {$method} not available");
		}

		$plainText = 'Testing AES-192-CBC';
		$encrypted = Crypt::encrypt($plainText, self::KEY, $method);
		$decrypted = Crypt::decrypt($encrypted, self::KEY, $method);
		$this->assertSame($plainText, $decrypted);
	}

}

