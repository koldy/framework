<?php

declare(strict_types=1);

namespace Tests;

use Koldy\Application;
use Koldy\Cookie;
use Koldy\Crypt;
use Koldy\Mock;
use PHPUnit\Framework\TestCase;

class CookieTest extends TestCase
{

    private static bool $appInitialized = false;

    /**
     * Bootstrap Application in test mode so Mock and Crypt work
     */
    public static function setUpBeforeClass(): void
    {
        if (!self::$appInitialized) {
            $_SERVER['SCRIPT_FILENAME'] = __FILE__;

            Application::useConfig([
                'site_url' => 'http://localhost',
                'env' => Application::TEST,
                'key' => 'CookieTestKey1234567',
                'timezone' => 'UTC',
                'paths' => [
                    'application' => __DIR__ . '/',
                    'storage' => __DIR__ . '/',
                ],
            ]);

            self::$appInitialized = true;
        }
    }

    protected function setUp(): void
    {
        Mock::start();
        $_COOKIE = [];
    }

    protected function tearDown(): void
    {
        Mock::reset();
    }

    // ── has ──

    public function testHasReturnsFalseWhenCookieDoesNotExist(): void
    {
        $this->assertFalse(Cookie::has('nonexistent'));
    }

    public function testHasReturnsTrueWhenCookieExists(): void
    {
        $_COOKIE['test'] = 'value';
        $this->assertTrue(Cookie::has('test'));
    }

    public function testHasReturnsTrueForEmptyStringValue(): void
    {
        $_COOKIE['empty'] = '';
        $this->assertTrue(Cookie::has('empty'));
    }

    // ── rawGet ──

    public function testRawGetReturnsNullWhenCookieDoesNotExist(): void
    {
        $this->assertNull(Cookie::rawGet('missing'));
    }

    public function testRawGetReturnsValueWhenCookieExists(): void
    {
        $_COOKIE['name'] = 'John';
        $this->assertSame('John', Cookie::rawGet('name'));
    }

    public function testRawGetReturnsEmptyStringWhenCookieIsEmpty(): void
    {
        $_COOKIE['blank'] = '';
        $this->assertSame('', Cookie::rawGet('blank'));
    }

    public function testRawGetReturnsSpecialCharacters(): void
    {
        $_COOKIE['special'] = 'value with spaces & symbols!@#$%';
        $this->assertSame('value with spaces & symbols!@#$%', Cookie::rawGet('special'));
    }

    // ── rawSet ──

    /**
     * @runInSeparateProcess
     */
    public function testRawSetReturnsSameValue(): void
    {
        $result = Cookie::rawSet('test', 'hello');
        $this->assertSame('hello', $result);
    }

    /**
     * @runInSeparateProcess
     */
    public function testRawSetReturnsEmptyStringForEmptyValue(): void
    {
        $result = Cookie::rawSet('test', '');
        $this->assertSame('', $result);
    }

    /**
     * @runInSeparateProcess
     */
    public function testRawSetWithAllOptions(): void
    {
        $result = Cookie::rawSet(
            'full',
            'value',
            time() + 3600,
            '/app',
            'example.com',
            true,
            true,
            'Strict'
        );
        $this->assertSame('value', $result);
    }

    // ── get (encrypted) ──

    public function testGetReturnsNullWhenCookieDoesNotExist(): void
    {
        $this->assertNull(Cookie::get('missing'));
    }

    public function testGetDecryptsValueFromCookie(): void
    {
        $encrypted = Crypt::encrypt('secret_value');
        $_COOKIE['encrypted_cookie'] = $encrypted;

        $this->assertSame('secret_value', Cookie::get('encrypted_cookie'));
    }

    public function testGetThrowsOnEmptyStringCookie(): void
    {
        // ctype_print('') returns false in PHP, so decrypting an empty string
        // through Cookie::get will throw MalformedException
        $encrypted = Crypt::encrypt('');
        $_COOKIE['empty_encrypted'] = $encrypted;

        $this->expectException(\Koldy\Crypt\MalformedException::class);
        Cookie::get('empty_encrypted');
    }

    public function testGetDecryptsSpecialCharacters(): void
    {
        $value = 'Special: !@#$%^&*()_+-=[]{}|;:,.<>?';
        $encrypted = Crypt::encrypt($value);
        $_COOKIE['special_encrypted'] = $encrypted;

        $this->assertSame($value, Cookie::get('special_encrypted'));
    }

    // ── set (encrypted) ──

    /**
     * @runInSeparateProcess
     */
    public function testSetReturnsEncryptedValue(): void
    {
        $encrypted = Cookie::set('my_cookie', 'my_value');
        $this->assertNotEmpty($encrypted);
        $this->assertNotSame('my_value', $encrypted);
        $this->assertStringContainsString(':', $encrypted);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSetEncryptedValueCanBeDecrypted(): void
    {
        $encrypted = Cookie::set('my_cookie', 'decryptable');
        $decrypted = Crypt::decrypt($encrypted);
        $this->assertSame('decryptable', $decrypted);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSetWithAllOptions(): void
    {
        $encrypted = Cookie::set(
            'full_cookie',
            'full_value',
            time() + 7200,
            '/secure',
            'secure.example.com',
            true,
            true,
            'Lax'
        );
        $this->assertNotEmpty($encrypted);
        $decrypted = Crypt::decrypt($encrypted);
        $this->assertSame('full_value', $decrypted);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSetProducesDifferentEncryptedValuesEachTime(): void
    {
        $a = Cookie::set('cookie_a', 'same_value');
        $b = Cookie::set('cookie_b', 'same_value');
        // Due to random IV, same plaintext should produce different ciphertext
        $this->assertNotSame($a, $b);
    }

    // ── delete ──

    /**
     * @runInSeparateProcess
     */
    public function testDeleteDoesNotThrow(): void
    {
        Cookie::delete('some_cookie');
        $this->assertTrue(true); // if we got here, no exception was thrown
    }

    /**
     * @runInSeparateProcess
     */
    public function testDeleteWithAllOptions(): void
    {
        Cookie::delete(
            'full_delete',
            '/app',
            'example.com',
            true,
            true,
            'None'
        );
        $this->assertTrue(true);
    }

    // ── Mock integration ("real" cases) ──

    public function testMockRequestThenReadCookie(): void
    {
        // Simulate a request, then manually set a cookie as if the browser sent it
        Mock::request('GET', '/some-page');
        $_COOKIE['session_id'] = 'abc123';

        $this->assertTrue(Cookie::has('session_id'));
        $this->assertSame('abc123', Cookie::rawGet('session_id'));
        $this->assertFalse(Cookie::has('other_cookie'));
    }

    public function testMockRequestWithEncryptedCookie(): void
    {
        Mock::request('POST', '/login', ['user' => 'admin']);

        // Simulate an encrypted cookie that was previously set
        $encrypted = Crypt::encrypt('user_token_12345');
        $_COOKIE['auth_token'] = $encrypted;

        $this->assertTrue(Cookie::has('auth_token'));
        $this->assertSame('user_token_12345', Cookie::get('auth_token'));
    }

    public function testMockResetClearsCookies(): void
    {
        Mock::start();
        $_COOKIE['temp'] = 'temporary';
        $this->assertTrue(Cookie::has('temp'));

        Mock::reset();
        // After reset, $_COOKIE should be restored to original (empty in test)
        $this->assertFalse(Cookie::has('temp'));
    }

    public function testMultipleCookiesCoexist(): void
    {
        $_COOKIE['first'] = 'one';
        $_COOKIE['second'] = 'two';
        $_COOKIE['third'] = 'three';

        $this->assertTrue(Cookie::has('first'));
        $this->assertTrue(Cookie::has('second'));
        $this->assertTrue(Cookie::has('third'));
        $this->assertFalse(Cookie::has('fourth'));

        $this->assertSame('one', Cookie::rawGet('first'));
        $this->assertSame('two', Cookie::rawGet('second'));
        $this->assertSame('three', Cookie::rawGet('third'));
        $this->assertNull(Cookie::rawGet('fourth'));
    }

    public function testEncryptedAndRawCookiesCoexist(): void
    {
        $encrypted = Crypt::encrypt('secret');
        $_COOKIE['encrypted'] = $encrypted;
        $_COOKIE['plain'] = 'not_encrypted';

        $this->assertSame('secret', Cookie::get('encrypted'));
        $this->assertSame('not_encrypted', Cookie::rawGet('plain'));
    }

}

