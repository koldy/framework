<?php

declare(strict_types=1);

namespace Tests;

use Koldy\Convert;
use Koldy\Convert\Exception as ConvertException;
use PHPUnit\Framework\TestCase;

class ConvertTest extends TestCase
{

	// ── bytesToString ──

	public function testBytesToStringZero(): void
	{
		$this->assertSame('0 B', Convert::bytesToString(0));
	}

	public function testBytesToStringBytes(): void
	{
		$this->assertSame('512 B', Convert::bytesToString(512));
	}

	public function testBytesToStringKilobytes(): void
	{
		$this->assertSame('2 KB', Convert::bytesToString(2048));
	}

	public function testBytesToStringMegabytes(): void
	{
		$this->assertSame('1 MB', Convert::bytesToString(1048576));
	}

	public function testBytesToStringGigabytes(): void
	{
		$this->assertSame('1 GB', Convert::bytesToString(1073741824));
	}

	public function testBytesToStringWithRounding(): void
	{
		// 1.5 KB = 1536 bytes
		$this->assertSame('1.5 KB', Convert::bytesToString(1536, 1));
	}

	public function testBytesToStringWithMoreDecimals(): void
	{
		// 1536 bytes = 1.50 KB with 2 decimals
		$this->assertSame('1.5 KB', Convert::bytesToString(1536, 2));
	}

	public function testBytesToStringTerabytes(): void
	{
		$this->assertSame('1 TB', Convert::bytesToString(1099511627776));
	}

	public function testBytesToStringExactKilobyte(): void
	{
		$this->assertSame('1 KB', Convert::bytesToString(1024));
	}

	// ── stringToBytes ──

	public function testStringToBytesPlainNumber(): void
	{
		$this->assertSame(1024, Convert::stringToBytes('1024'));
	}

	public function testStringToBytesZero(): void
	{
		$this->assertSame(0, Convert::stringToBytes('0'));
	}

	public function testStringToBytesKilobytes(): void
	{
		$this->assertSame(1024, Convert::stringToBytes('1K'));
	}

	public function testStringToBytesMegabytes(): void
	{
		$this->assertSame(1048576, Convert::stringToBytes('1M'));
	}

	public function testStringToBytesGigabytes(): void
	{
		$this->assertSame(1073741824, Convert::stringToBytes('1G'));
	}

	public function testStringToBytesTerabytes(): void
	{
		$this->assertSame(1099511627776, Convert::stringToBytes('1T'));
	}

	public function testStringToBytesPetabytes(): void
	{
		$this->assertSame(1125899906842624, Convert::stringToBytes('1P'));
	}

	public function testStringToBytesExabytes(): void
	{
		$this->assertSame(1152921504606846976, Convert::stringToBytes('1E'));
	}

	public function testStringToBytesLowercaseSuffix(): void
	{
		$this->assertSame(1048576, Convert::stringToBytes('1m'));
	}

	public function testStringToBytesTrimsWhitespace(): void
	{
		$this->assertSame(1048576, Convert::stringToBytes('  1M  '));
	}

	public function testStringToBytesMultipleKilobytes(): void
	{
		$this->assertSame(2048, Convert::stringToBytes('2K'));
	}

	public function testStringToBytesLargeMultiplier(): void
	{
		$this->assertSame(524288000, Convert::stringToBytes('500M'));
	}

	public function testStringToBytesUnsupportedSuffixThrows(): void
	{
		$this->expectException(ConvertException::class);
		$this->expectExceptionMessage('Not implemented sizes greater than exabytes');
		Convert::stringToBytes('1Z');
	}

	// ── stringToUtf8 ──

	public function testStringToUtf8AlreadyUtf8(): void
	{
		$input = 'Hello World';
		$this->assertSame($input, Convert::stringToUtf8($input));
	}

	public function testStringToUtf8WithUtf8Multibyte(): void
	{
		$input = 'Héllo Wörld ñ';
		$this->assertSame($input, Convert::stringToUtf8($input));
	}

	public function testStringToUtf8EmptyString(): void
	{
		$this->assertSame('', Convert::stringToUtf8(''));
	}

	public function testStringToUtf8WithEmoji(): void
	{
		$input = 'Hello 🌍';
		$this->assertSame($input, Convert::stringToUtf8($input));
	}

	public function testStringToUtf8Latin1Conversion(): void
	{
		// Latin-1 encoded string (ü = 0xFC in Latin-1)
		$latin1 = "\xFC";
		$result = Convert::stringToUtf8($latin1);
		$this->assertTrue(mb_check_encoding($result, 'UTF-8'));
	}

}
