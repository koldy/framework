<?php

declare(strict_types=1);

namespace Tests\Response;

use Koldy\Json as KoldyJson;
use Koldy\Response\AbstractResponse;
use Koldy\Response\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Koldy\Response\Json::flush(), focused on Content-Length being the
 * BYTE length of the emitted body (RFC 9110 §8.6), not the character count.
 *
 * NOTE: tests/JsonTest.php (root) covers the Koldy\Json UTILITY class; this file
 * covers the Koldy\Response\Json RESPONSE class, which had no coverage before.
 */
class JsonTest extends TestCase
{

	/**
	 * Build a Json that isolates the unit under test: flushHeaders() is a no-op
	 * (so flush() records headers in memory, readable via getHeaders(), but emits
	 * no real HTTP headers) and runAfterFlush() is a no-op (so the post-flush
	 * lifecycle — Session::close(), Application::setResponse() — cannot leak global
	 * state into, or break, this Content-Length test).
	 */
	private static function silentJson(array $data = []): Json
	{
		return new class ($data) extends Json {
			public function flushHeaders(): void
			{
				// no-op: keep the in-memory headers, emit no real HTTP headers
			}

			protected function runAfterFlush(): void
			{
				// no-op: skip session/response lifecycle side effects
			}
		};
	}

	/**
	 * Flush the response inside an output buffer and return [emittedBody, headers].
	 */
	private function flushAndCapture(AbstractResponse $response): array
	{
		ob_start();

		try {
			$response->flush();
		} finally {
			$body = ob_get_clean();
		}

		return [$body, $response->getHeaders()];
	}

	// ── raw-content path (setContent/fromString): carries genuine multibyte bytes ──

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function rawJsonProvider(): array
	{
		return [
			'ascii'     => ['{"a":1}'],
			'latin'     => ['{"msg":"čćšžđ"}'],
			'euro'      => ['{"price":"5 €"}'],
			'emoji'     => ['{"m":"🎉"}'],
		];
	}

	#[DataProvider('rawJsonProvider')]
	public function testFlushRawContentSetsByteLengthContentLength(string $raw): void
	{
		$json = self::silentJson()->setContent($raw);

		[$emitted, $headers] = $this->flushAndCapture($json);

		$this->assertSame($raw, $emitted, 'raw JSON must be emitted byte-for-byte');
		$this->assertContains('Content-Length: ' . strlen($raw), $headers);
	}

	public function testFlushRawContentLengthIsBytesNotCharsForMultibyte(): void
	{
		$raw = '{"msg":"č€ž"}';
		$bytes = strlen($raw);
		$chars = mb_strlen($raw, 'UTF-8');

		// guard: this fixture actually exercises the byte-vs-char difference
		$this->assertNotSame($bytes, $chars);

		$json = self::silentJson()->setContent($raw);

		[, $headers] = $this->flushAndCapture($json);

		$this->assertContains('Content-Length: ' . $bytes, $headers);
		$this->assertNotContains('Content-Length: ' . $chars, $headers);
	}

	// ── data path (Json::create): json_encode escapes unicode to ASCII \uXXXX ──

	/**
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public static function dataPathProvider(): array
	{
		return [
			'ascii'     => [['a' => 1, 'b' => 'two']],
			'multibyte' => [['msg' => 'č€ž']], // encodes to ASCII \uXXXX, still byte-correct
		];
	}

	#[DataProvider('dataPathProvider')]
	public function testFlushDataPathContentLengthMatchesEncodedByteLength(array $data): void
	{
		$json = self::silentJson($data);

		[$emitted, $headers] = $this->flushAndCapture($json);

		$this->assertContains('Content-Length: ' . strlen($emitted), $headers);
		// round-trip: emitted JSON decodes back to the original data
		$this->assertSame($data, KoldyJson::decode($emitted));
	}

	// ── empty-array bodies ("{}" / "[]") ──

	public function testFlushEmptyAssociativeArrayEmitsObjectWithLengthTwo(): void
	{
		[$emitted, $headers] = $this->flushAndCapture(self::silentJson([]));

		$this->assertSame('{}', $emitted);
		$this->assertContains('Content-Length: 2', $headers);
	}

	public function testFlushEmptyNonAssociativeArrayEmitsArrayWithLengthTwo(): void
	{
		$json = self::silentJson([]);
		$json->setAssociativeArray(false);

		[$emitted, $headers] = $this->flushAndCapture($json);

		$this->assertSame('[]', $emitted);
		$this->assertContains('Content-Length: 2', $headers);
	}

	// ── status-code edge cases ──

	public function testFlush204EmitsZeroContentLengthAndEmptyBody(): void
	{
		$json = self::silentJson(['msg' => 'č€ž'])->statusCode(204);

		[$emitted, $headers] = $this->flushAndCapture($json);

		$this->assertSame('', $emitted);
		$this->assertContains('Content-Length: 0', $headers);
	}

	public function testFlush1xxDoesNotSetContentLength(): void
	{
		$json = self::silentJson(['a' => 1])->statusCode(100);

		$this->flushAndCapture($json);

		$this->assertFalse($json->hasHeader('Content-Length'));
	}

	public function testFlushKeepsContentTypeAlongsideContentLength(): void
	{
		$raw = '{"msg":"č€ž"}';
		$json = self::silentJson()->setContent($raw);

		[, $headers] = $this->flushAndCapture($json);

		$this->assertContains('Content-Type: application/json', $headers);
		$this->assertContains('Content-Length: ' . strlen($raw), $headers);
	}

}
