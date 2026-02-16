<?php

declare(strict_types=1);

namespace Tests\Convert;

use Koldy\Convert\Exception;
use Koldy\Convert\NumericNotation;
use PHPUnit\Framework\TestCase;

class NumericNotationTest extends TestCase
{

	// ── dec2big ──

	public function testDec2BigZero(): void
	{
		$this->assertSame('0', NumericNotation::dec2big('0'));
	}

	public function testDec2BigSingleDigit(): void
	{
		$this->assertSame('9', NumericNotation::dec2big('9'));
	}

	public function testDec2BigDocExample(): void
	{
		// @example from the class: 40487 is ax1
		$this->assertSame('ax1', NumericNotation::dec2big('40487'));
	}

	public function testDec2BigSmallNumbers(): void
	{
		$this->assertSame('1', NumericNotation::dec2big('1'));
		$this->assertSame('a', NumericNotation::dec2big('10'));
		$this->assertSame('A', NumericNotation::dec2big('36'));
		$this->assertSame('Z', NumericNotation::dec2big('61'));
	}

	public function testDec2BigBase62Boundary(): void
	{
		// 62 in base-62 should be "10"
		$this->assertSame('10', NumericNotation::dec2big('62'));
	}

	public function testDec2BigLargeNumber(): void
	{
		// 62^2 = 3844 should be "100"
		$this->assertSame('100', NumericNotation::dec2big('3844'));
	}

	public function testDec2BigVeryLargeNumber(): void
	{
		// Test with a number larger than PHP_INT_MAX to exercise bcmath
		$big = '999999999999999999999999999999';
		$result = NumericNotation::dec2big($big);
		$this->assertNotEmpty($result);
		// Verify roundtrip
		$this->assertSame($big, NumericNotation::big2dec($result));
	}

	public function testDec2BigTrimsWhitespace(): void
	{
		$this->assertSame('ax1', NumericNotation::dec2big('  40487  '));
	}

	public function testDec2BigEmptyStringThrows(): void
	{
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Got empty number for dec2big');
		NumericNotation::dec2big('');
	}

	public function testDec2BigWhitespaceOnlyThrows(): void
	{
		$this->expectException(Exception::class);
		NumericNotation::dec2big('   ');
	}

	// ── big2dec ──

	public function testBig2DecZero(): void
	{
		$this->assertSame('0', NumericNotation::big2dec('0'));
	}

	public function testBig2DecSingleDigit(): void
	{
		$this->assertSame('9', NumericNotation::big2dec('9'));
	}

	public function testBig2DecDocExample(): void
	{
		// @example from the class: ax1 is 40487
		$this->assertSame('40487', NumericNotation::big2dec('ax1'));
	}

	public function testBig2DecLetters(): void
	{
		$this->assertSame('10', NumericNotation::big2dec('a'));
		$this->assertSame('36', NumericNotation::big2dec('A'));
		$this->assertSame('61', NumericNotation::big2dec('Z'));
	}

	public function testBig2DecBase62Boundary(): void
	{
		$this->assertSame('62', NumericNotation::big2dec('10'));
	}

	public function testBig2DecEmptyStringThrows(): void
	{
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Got empty string in big2dec');
		NumericNotation::big2dec('');
	}

	public function testBig2DecInvalidCharacterThrows(): void
	{
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Invalid numeric notation character');
		NumericNotation::big2dec('abc!def');
	}

	// ── roundtrip conversions ──

	public function testRoundtripSmallNumbers(): void
	{
		for ($i = 0; $i < 100; $i++) {
			$dec = (string)$i;
			$this->assertSame($dec, NumericNotation::big2dec(NumericNotation::dec2big($dec)), "Roundtrip failed for {$dec}");
		}
	}

	public function testRoundtripMediumNumbers(): void
	{
		$numbers = ['100', '999', '1000', '3843', '3844', '3845', '10000', '100000', '999999'];
		foreach ($numbers as $dec) {
			$this->assertSame($dec, NumericNotation::big2dec(NumericNotation::dec2big($dec)), "Roundtrip failed for {$dec}");
		}
	}

	public function testRoundtripLargeNumbers(): void
	{
		$numbers = ['2147483647', '9999999999', '99999999999999999999'];
		foreach ($numbers as $dec) {
			$this->assertSame($dec, NumericNotation::big2dec(NumericNotation::dec2big($dec)), "Roundtrip failed for {$dec}");
		}
	}

	public function testDec2BigProducesShorterRepresentation(): void
	{
		// A large decimal number should produce a shorter base-62 string
		$dec = '999999999999';
		$big = NumericNotation::dec2big($dec);
		$this->assertLessThan(strlen($dec), strlen($big));
	}

}
