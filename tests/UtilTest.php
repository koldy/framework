<?php

declare(strict_types=1);

namespace Tests;

use Koldy\Util;
use PHPUnit\Framework\TestCase;

class UtilTest extends TestCase
{

	// ── randomString ──

	public function testRandomStringReturnsCorrectLength(): void
	{
		$result = Util::randomString(16);
		$this->assertSame(16, strlen($result));
	}

	public function testRandomStringContainsOnlyAlphanumeric(): void
	{
		$result = Util::randomString(100);
		$this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $result);
	}

	public function testRandomStringLengthOne(): void
	{
		$result = Util::randomString(1);
		$this->assertSame(1, strlen($result));
	}

	public function testRandomStringLengthZeroThrows(): void
	{
		$this->expectException(\ValueError::class);
		Util::randomString(0);
	}

	// ── str2hex ──

	public function testStr2HexBasic(): void
	{
		$this->assertSame('48656C6C6F', Util::str2hex('Hello'));
	}

	public function testStr2HexEmptyString(): void
	{
		$this->assertSame('', Util::str2hex(''));
	}

	public function testStr2HexSingleChar(): void
	{
		$this->assertSame('41', Util::str2hex('A'));
	}

	// ── cleanString ──

	public function testCleanStringRemovesTabsAndNewlines(): void
	{
		$this->assertSame('hello world', Util::cleanString("hello\t\nworld"));
	}

	public function testCleanStringRemovesDoubleSpaces(): void
	{
		$this->assertSame('hello world', Util::cleanString('hello    world'));
	}

	public function testCleanStringEmptyString(): void
	{
		$this->assertSame('', Util::cleanString(''));
	}

	public function testCleanStringTrimsResult(): void
	{
		$this->assertSame('hello', Util::cleanString('  hello  '));
	}

	// ── truncate ──

	public function testTruncateZeroLength(): void
	{
		$this->assertSame('', Util::truncate('Hello World', 0));
	}

	public function testTruncateShortStringUnchanged(): void
	{
		$this->assertSame('Hello', Util::truncate('Hello', 80));
	}

	public function testTruncateLongStringWithEllipsis(): void
	{
		$result = Util::truncate('Hello World this is a long string', 10);
		$this->assertLessThanOrEqual(10, mb_strlen($result));
		$this->assertStringEndsWith('...', $result);
	}

	public function testTruncateBreakWords(): void
	{
		$result = Util::truncate('Hello World', 8, '...', true);
		$this->assertSame('Hello...', $result);
	}

	public function testTruncateMiddle(): void
	{
		$result = Util::truncate('Hello World this is long', 15, '...', false, true);
		$this->assertStringContainsString('...', $result);
	}

	public function testTruncateCustomEtc(): void
	{
		$result = Util::truncate('Hello World this is a long string', 10, '--');
		$this->assertStringEndsWith('--', $result);
	}

	// ── p ──

	public function testPWrapsInParagraph(): void
	{
		$this->assertSame('<p>Hello</p>', Util::p('Hello'));
	}

	public function testPConvertsDoubleNewlineToParagraphs(): void
	{
		$this->assertSame('<p>Hello</p><p>World</p>', Util::p("Hello\n\nWorld"));
	}

	public function testPConvertsSingleNewlineToBr(): void
	{
		$this->assertSame('<p>Hello<br/>World</p>', Util::p("Hello\nWorld"));
	}

	// ── a ──

	public function testAConvertsUrlToLink(): void
	{
		$result = Util::a('Visit https://example.com today');
		$this->assertStringContainsString('<a href="https://example.com"', $result);
		$this->assertStringContainsString('</a>', $result);
	}

	public function testAWithTarget(): void
	{
		$result = Util::a('Visit https://example.com today', '_blank');
		$this->assertStringContainsString('target="_blank"', $result);
	}

	public function testAWithoutTarget(): void
	{
		$result = Util::a('Visit https://example.com today');
		$this->assertStringNotContainsString('target=', $result);
	}

	public function testAPlainTextUnchanged(): void
	{
		$this->assertSame('no links here', Util::a('no links here'));
	}

	// ── attributeValue ──

	public function testAttributeValueEscapesAll(): void
	{
		$result = Util::attributeValue('<div class="test">it\'s</div>');
		$this->assertStringNotContainsString('<', $result);
		$this->assertStringNotContainsString('>', $result);
		$this->assertStringNotContainsString('"', $result);
		$this->assertStringNotContainsString("'", $result);
	}

	// ── quotes ──

	public function testQuotesReplacesDoubleQuotes(): void
	{
		$this->assertSame('&quot;hello&quot;', Util::quotes('"hello"'));
	}

	public function testQuotesNoQuotes(): void
	{
		$this->assertSame('hello', Util::quotes('hello'));
	}

	// ── apos ──

	public function testAposReplacesApostrophes(): void
	{
		$this->assertSame('it&apos;s', Util::apos("it's"));
	}

	public function testAposNoApostrophes(): void
	{
		$this->assertSame('hello', Util::apos('hello'));
	}

	// ── tags ──

	public function testTagsReplacesAngleBrackets(): void
	{
		$this->assertSame('&lt;div&gt;', Util::tags('<div>'));
	}

	public function testTagsNoAngleBrackets(): void
	{
		$this->assertSame('hello', Util::tags('hello'));
	}

	// ── startsWith ──

	public function testStartsWithTrue(): void
	{
		$this->assertTrue(Util::startsWith('Hello World', 'Hello', 'UTF-8'));
	}

	public function testStartsWithFalse(): void
	{
		$this->assertFalse(Util::startsWith('Hello World', 'World', 'UTF-8'));
	}

	public function testStartsWithEmptyPrefix(): void
	{
		$this->assertTrue(Util::startsWith('Hello', '', 'UTF-8'));
	}

	public function testStartsWithCaseSensitive(): void
	{
		$this->assertFalse(Util::startsWith('Hello', 'hello', 'UTF-8'));
	}

	// ── endsWith ──

	public function testEndsWithTrue(): void
	{
		$this->assertTrue(Util::endsWith('Hello World', 'World', 'UTF-8'));
	}

	public function testEndsWithFalse(): void
	{
		$this->assertFalse(Util::endsWith('Hello World', 'Hello', 'UTF-8'));
	}

	public function testEndsWithEmptySuffix(): void
	{
		// mb_substr with 0 length from end returns empty string which doesn't match ''
		// endsWith('Hello', '') returns false because mb_substr('Hello', 0, null) === 'Hello' !== ''
		$this->assertFalse(Util::endsWith('Hello', '', 'UTF-8'));
	}

	public function testEndsWithCaseSensitive(): void
	{
		$this->assertFalse(Util::endsWith('Hello World', 'world', 'UTF-8'));
	}

	// ── slug ──

	public function testSlugBasic(): void
	{
		$this->assertSame('your-new-title', Util::slug('Your new - title'));
	}

	public function testSlugEmptyString(): void
	{
		$this->assertSame('', Util::slug(''));
	}

	public function testSlugWithSpecialCharacters(): void
	{
		$result = Util::slug('Vozač napravio 1500€ štete');
		$this->assertSame('vozac-napravio-1500eur-stete', $result);
	}

	public function testSlugWithAccentedCharacters(): void
	{
		$result = Util::slug('Café résumé');
		$this->assertSame('cafe-resume', $result);
	}

	public function testSlugWithCurrencySymbols(): void
	{
		$this->assertStringContainsString('usd', Util::slug('Price $100'));
		$this->assertStringContainsString('pound', Util::slug('Price £100'));
		$this->assertStringContainsString('yen', Util::slug('Price ¥100'));
	}

	public function testSlugNoDoubleHyphens(): void
	{
		$result = Util::slug('Hello   ---   World');
		$this->assertStringNotContainsString('--', $result);
	}

	// ── camelCase ──

	public function testCamelCaseBasic(): void
	{
		$this->assertSame('helloWorld', Util::camelCase('hello world'));
	}

	public function testCamelCaseWithHyphens(): void
	{
		$this->assertSame('helloWorld', Util::camelCase('hello-world'));
	}

	public function testCamelCaseWithUnderscores(): void
	{
		$this->assertSame('helloWorld', Util::camelCase('hello_world'));
	}

	public function testCamelCaseUpperFirst(): void
	{
		$this->assertSame('HelloWorld', Util::camelCase('hello world', null, false));
	}

	public function testCamelCaseLowerFirst(): void
	{
		$this->assertSame('helloWorld', Util::camelCase('hello world', null, true));
	}

	public function testCamelCaseWithNoStrip(): void
	{
		$result = Util::camelCase('hello_world', ['_']);
		$this->assertSame('hello_world', $result);
	}

	// ── isAssociativeArray ──

	public function testIsAssociativeArrayWithSequentialArray(): void
	{
		$this->assertFalse(Util::isAssociativeArray([1, 2, 3]));
	}

	public function testIsAssociativeArrayWithAssociativeArray(): void
	{
		$this->assertTrue(Util::isAssociativeArray(['a' => 1, 'b' => 2]));
	}

	public function testIsAssociativeArrayWithEmptyArray(): void
	{
		$this->assertFalse(Util::isAssociativeArray([]));
	}

	public function testIsAssociativeArrayWithMixedKeys(): void
	{
		$this->assertTrue(Util::isAssociativeArray([0 => 'a', 2 => 'b']));
	}

	// ── isBinary ──

	public function testIsBinaryWithBinaryString(): void
	{
		$this->assertTrue(Util::isBinary("\x00\x01\x02"));
	}

	public function testIsBinaryWithRegularString(): void
	{
		$this->assertFalse(Util::isBinary('Hello World'));
	}

	public function testIsBinaryWithEmptyString(): void
	{
		$this->assertFalse(Util::isBinary(''));
	}

	public function testIsBinaryWithNonString(): void
	{
		$this->assertFalse(Util::isBinary(123));
	}

	// ── randomUUIDv4 ──

	public function testRandomUUIDv4Format(): void
	{
		$uuid = Util::randomUUIDv4();
		$this->assertMatchesRegularExpression(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
			$uuid
		);
	}

	public function testRandomUUIDv4Uniqueness(): void
	{
		$uuid1 = Util::randomUUIDv4();
		$uuid2 = Util::randomUUIDv4();
		$this->assertNotSame($uuid1, $uuid2);
	}

	// ── now ──

	public function testNowDefaultFormat(): void
	{
		$result = Util::now();
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d+$/', $result);
	}

	public function testNowCustomFormat(): void
	{
		$result = Util::now('Y-m-d');
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $result);
	}

	public function testNowCustomTimezone(): void
	{
		$utc = Util::now('Y-m-d H:i', 'UTC');
		$this->assertNotEmpty($utc);
	}

	// ── parseMultipartContent ──

	public function testParseMultipartContentEmptyInput(): void
	{
		$this->assertSame([], Util::parseMultipartContent('', 'multipart/form-data; boundary=----WebKitFormBoundary'));
	}

	public function testParseMultipartContentBasic(): void
	{
		$boundary = '----WebKitFormBoundary7MA4YWxkTrZu0gW';
		$contentType = "multipart/form-data; boundary={$boundary}";

		$input = "------WebKitFormBoundary7MA4YWxkTrZu0gW\r\n";
		$input .= "Content-Disposition: form-data; name=\"field1\"\r\n\r\n";
		$input .= "value1\r\n";
		$input .= "------WebKitFormBoundary7MA4YWxkTrZu0gW\r\n";
		$input .= "Content-Disposition: form-data; name=\"field2\"\r\n\r\n";
		$input .= "value2\r\n";
		$input .= "------WebKitFormBoundary7MA4YWxkTrZu0gW--\r\n";

		$result = Util::parseMultipartContent($input, $contentType);
		$this->assertArrayHasKey('field1', $result);
		$this->assertArrayHasKey('field2', $result);
		$this->assertSame('value1', $result['field1']);
		$this->assertSame('value2', $result['field2']);
	}

	// ── pick ──

	public function testPickExistingKey(): void
	{
		$this->assertSame('bar', Util::pick('foo', ['foo' => 'bar']));
	}

	public function testPickMissingKeyReturnsDefault(): void
	{
		$this->assertNull(Util::pick('missing', ['foo' => 'bar']));
	}

	public function testPickMissingKeyReturnsCustomDefault(): void
	{
		$this->assertSame('default', Util::pick('missing', ['foo' => 'bar'], 'default'));
	}

	public function testPickNullValueReturnsDefault(): void
	{
		$this->assertSame('default', Util::pick('foo', ['foo' => null], 'default'));
	}

	public function testPickWithAllowedValuesAccepted(): void
	{
		$this->assertSame('yes', Util::pick('foo', ['foo' => 'yes'], 'no', ['yes', 'no']));
	}

	public function testPickWithAllowedValuesRejected(): void
	{
		$this->assertSame('default', Util::pick('foo', ['foo' => 'maybe'], 'default', ['yes', 'no']));
	}

	public function testPickWithAllowedValuesStrictComparison(): void
	{
		// '1' (string) should not match 1 (int) in strict comparison
		$this->assertSame('default', Util::pick('foo', ['foo' => '1'], 'default', [1, 2, 3]));
	}

	// ── getRelativePath ──

	public function testGetRelativePathSameDirectory(): void
	{
		$result = Util::getRelativePath('/var/www/html/index.php', '/var/www/html/about.php');
		$this->assertSame('./about.php', $result);
	}

	public function testGetRelativePathSubDirectory(): void
	{
		$result = Util::getRelativePath('/var/www/html/index.php', '/var/www/html/sub/page.php');
		$this->assertSame('./sub/page.php', $result);
	}

	public function testGetRelativePathParentDirectory(): void
	{
		$result = Util::getRelativePath('/var/www/html/sub/index.php', '/var/www/html/about.php');
		$this->assertSame('../about.php', $result);
	}

}

