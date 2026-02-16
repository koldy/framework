<?php

declare(strict_types=1);

namespace Tests;

use Koldy\Url;
use Koldy\Url\Exception as UrlException;
use PHPUnit\Framework\TestCase;

class UrlTest extends TestCase
{

	// ── constructor / getUrl ──

	public function testConstructorStoresUrl(): void
	{
		$url = new Url('https://example.com/path');
		$this->assertSame('https://example.com/path', $url->getUrl());
	}

	public function testToStringReturnsUrl(): void
	{
		$url = new Url('https://example.com/path');
		$this->assertSame('https://example.com/path', (string)$url);
	}

	// ── getScheme ──

	public function testGetSchemeHttps(): void
	{
		$url = new Url('https://example.com');
		$this->assertSame('https', $url->getScheme());
	}

	public function testGetSchemeHttp(): void
	{
		$url = new Url('http://example.com');
		$this->assertSame('http', $url->getScheme());
	}

	public function testGetSchemeFtp(): void
	{
		$url = new Url('ftp://files.example.com/file.txt');
		$this->assertSame('ftp', $url->getScheme());
	}

	public function testGetSchemeMissingThrows(): void
	{
		$url = new Url('/relative/path');
		$this->expectException(UrlException::class);
		$this->expectExceptionMessage('Unable to get scheme');
		$url->getScheme();
	}

	// ── getHost ──

	public function testGetHost(): void
	{
		$url = new Url('https://example.com/path');
		$this->assertSame('example.com', $url->getHost());
	}

	public function testGetHostWithSubdomain(): void
	{
		$url = new Url('https://www.example.com');
		$this->assertSame('www.example.com', $url->getHost());
	}

	public function testGetHostMissingThrows(): void
	{
		$url = new Url('/relative/path');
		$this->expectException(UrlException::class);
		$this->expectExceptionMessage('Unable to get host');
		$url->getHost();
	}

	// ── getPort ──

	public function testGetPort(): void
	{
		$url = new Url('https://example.com:8443/path');
		$this->assertSame(8443, $url->getPort());
	}

	public function testGetPortHttp(): void
	{
		$url = new Url('http://example.com:8080');
		$this->assertSame(8080, $url->getPort());
	}

	public function testGetPortMissingThrows(): void
	{
		$url = new Url('https://example.com/path');
		$this->expectException(UrlException::class);
		$this->expectExceptionMessage('Unable to get port');
		$url->getPort();
	}

	// ── getUser / getPass ──

	public function testGetUserAndPass(): void
	{
		$url = new Url('https://admin:secret@example.com/path');
		$this->assertSame('admin', $url->getUser());
		$this->assertSame('secret', $url->getPass());
	}

	public function testGetUserMissingThrows(): void
	{
		$url = new Url('https://example.com');
		$this->expectException(UrlException::class);
		$this->expectExceptionMessage('Unable to get user');
		$url->getUser();
	}

	public function testGetPassMissingThrows(): void
	{
		$url = new Url('https://example.com');
		$this->expectException(UrlException::class);
		$this->expectExceptionMessage('Unable to get pass');
		$url->getPass();
	}

	// ── getPath ──

	public function testGetPath(): void
	{
		$url = new Url('https://example.com/some/path');
		$this->assertSame('/some/path', $url->getPath());
	}

	public function testGetPathRoot(): void
	{
		$url = new Url('https://example.com/');
		$this->assertSame('/', $url->getPath());
	}

	public function testGetPathMissingThrows(): void
	{
		$url = new Url('https://example.com');
		$this->expectException(UrlException::class);
		$this->expectExceptionMessage('Unable to get path');
		$url->getPath();
	}

	// ── getQuery ──

	public function testGetQuery(): void
	{
		$url = new Url('https://example.com/path?foo=bar&baz=1');
		$this->assertSame('foo=bar&baz=1', $url->getQuery());
	}

	public function testGetQueryMissingThrows(): void
	{
		$url = new Url('https://example.com/path');
		$this->expectException(UrlException::class);
		$this->expectExceptionMessage('Unable to get query');
		$url->getQuery();
	}

	// ── getFragment ──

	public function testGetFragment(): void
	{
		$url = new Url('https://example.com/path#section1');
		$this->assertSame('section1', $url->getFragment());
	}

	public function testGetFragmentMissingThrows(): void
	{
		$url = new Url('https://example.com/path');
		$this->expectException(UrlException::class);
		$this->expectExceptionMessage('Unable to get fragment');
		$url->getFragment();
	}

	// ── full URL with all segments ──

	public function testFullUrlWithAllSegments(): void
	{
		$url = new Url('https://user:pass@example.com:8443/path/to/page?key=val#frag');
		$this->assertSame('https', $url->getScheme());
		$this->assertSame('example.com', $url->getHost());
		$this->assertSame(8443, $url->getPort());
		$this->assertSame('user', $url->getUser());
		$this->assertSame('pass', $url->getPass());
		$this->assertSame('/path/to/page', $url->getPath());
		$this->assertSame('key=val', $url->getQuery());
		$this->assertSame('frag', $url->getFragment());
	}

	public function testRelativePath(): void
	{
		$url = new Url('/relative/path');
		$this->assertSame('/relative/path', $url->getPath());
		$this->assertSame('/relative/path', $url->getUrl());
	}

}
