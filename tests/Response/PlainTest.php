<?php

declare(strict_types=1);

namespace Tests\Response;

use Koldy\Response\AbstractResponse;
use Koldy\Response\Plain;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PlainTest extends TestCase
{

	// ── construction ──

	public function testEmptyConstructorHasEmptyContent(): void
	{
		$plain = new Plain();
		$this->assertSame('', $plain->getContent());
	}

	public function testEmptyConstructorSetsContentTypeHeader(): void
	{
		$plain = new Plain();
		$this->assertTrue($plain->hasHeader('Content-Type'));
		$this->assertContains('Content-Type: text/plain', $plain->getHeaders());
	}

	public function testConstructorStoresGivenContent(): void
	{
		$plain = new Plain('hello world');
		$this->assertSame('hello world', $plain->getContent());
	}

	// ── static factory ──

	public function testCreateReturnsPlainInstance(): void
	{
		$plain = Plain::create('hi');
		$this->assertInstanceOf(Plain::class, $plain);
		$this->assertSame('hi', $plain->getContent());
	}

	public function testCreateWithoutArgumentsYieldsEmptyContent(): void
	{
		$plain = Plain::create();
		$this->assertSame('', $plain->getContent());
	}

	// ── setContent ──

	public function testSetContentReplacesContent(): void
	{
		$plain = new Plain('one');
		$plain->setContent('two');
		$this->assertSame('two', $plain->getContent());
	}

	public function testSetContentReturnsSelf(): void
	{
		$plain = new Plain();
		$this->assertSame($plain, $plain->setContent('x'));
	}

	// ── append ──

	public function testAppendAppendsToExistingContent(): void
	{
		$plain = new Plain('foo');
		$plain->append('bar');
		$this->assertSame('foobar', $plain->getContent());
	}

	public function testAppendOnEmptyContentBehavesLikeSetContent(): void
	{
		$plain = new Plain();
		$plain->append('bar');
		$this->assertSame('bar', $plain->getContent());
	}

	public function testAppendReturnsSelf(): void
	{
		$plain = new Plain();
		$this->assertSame($plain, $plain->append('x'));
	}

	// ── prepend ──

	public function testPrependPrependsToExistingContent(): void
	{
		$plain = new Plain('bar');
		$plain->prepend('foo');
		$this->assertSame('foobar', $plain->getContent());
	}

	public function testPrependOnEmptyContentBehavesLikeSetContent(): void
	{
		$plain = new Plain();
		$plain->prepend('foo');
		$this->assertSame('foo', $plain->getContent());
	}

	public function testPrependReturnsSelf(): void
	{
		$plain = new Plain();
		$this->assertSame($plain, $plain->prepend('x'));
	}

	// ── output / string casting ──

	public function testGetOutputReturnsContent(): void
	{
		$plain = new Plain('payload');
		$this->assertSame('payload', $plain->getOutput());
	}

	public function testToStringReturnsContent(): void
	{
		$plain = new Plain('payload');
		$this->assertSame('payload', (string) $plain);
	}

	// ── header persistence ──

	public function testContentTypeHeaderRemainsAfterContentMutations(): void
	{
		$plain = new Plain('a');
		$plain->setContent('b')->append('c')->prepend('z');
		$this->assertTrue($plain->hasHeader('Content-Type'));
		$this->assertContains('Content-Type: text/plain', $plain->getHeaders());
	}

	// ── fluent chain ──

	public function testFluentChainingProducesExpectedContent(): void
	{
		$plain = Plain::create()
			->setContent('a')
			->append('b')
			->prepend('c');

		$this->assertSame('cab', $plain->getContent());
	}

	// ── flush(): Content-Length must be the BYTE length (RFC 9110 §8.6) ──

	/**
	 * Build a Plain that isolates the unit under test: flushHeaders() is a no-op
	 * (so flush() records headers in memory, readable via getHeaders(), but emits
	 * no real HTTP headers) and runAfterFlush() is a no-op (so the post-flush
	 * lifecycle — Session::close(), Application::setResponse() — cannot leak global
	 * state into, or break, this Content-Length test).
	 */
	private static function silentPlain(string $content = ''): Plain
	{
		return new class ($content) extends Plain {
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

	/**
	 * @return array<string, array{0: string, 1: int}>
	 */
	public static function flushBodyProvider(): array
	{
		return [
			// label            => [body, expected BYTE length]
			'ascii'             => ['hello world', 11],
			'latin-multibyte'   => ['čćšžđ', 10],       // 5 chars, 2 bytes each
			'euro-mixed'        => ['5 € only', 10],    // € = 3 bytes, 8 chars total
			'mixed'             => ['č€ž-abc', 11],      // 7 chars, 11 bytes
			'emoji-4byte'       => ['party 🎉', 10],     // 🎉 = 4 bytes, 7 chars total
			'empty'             => ['', 0],
		];
	}

	#[DataProvider('flushBodyProvider')]
	public function testFlushSetsByteLengthContentLength(string $body, int $expectedBytes): void
	{
		// sanity: the fixture's byte length is what we claim it is
		$this->assertSame($expectedBytes, strlen($body), 'fixture byte-count');

		[$emitted, $headers] = $this->flushAndCapture(self::silentPlain($body));

		$this->assertSame($body, $emitted, 'body must be emitted byte-for-byte');
		$this->assertContains('Content-Length: ' . $expectedBytes, $headers);
	}

	public function testFlushContentLengthIsBytesNotCharsForMultibyteBody(): void
	{
		$body = 'č€ž-abc';
		$bytes = strlen($body);              // 11
		$chars = mb_strlen($body, 'UTF-8');  // 7

		// guard: this fixture actually exercises the byte-vs-char difference
		$this->assertNotSame($bytes, $chars);

		[, $headers] = $this->flushAndCapture(self::silentPlain($body));

		$this->assertContains('Content-Length: ' . $bytes, $headers);
		$this->assertNotContains('Content-Length: ' . $chars, $headers);
	}

	public function testFlushKeepsContentTypeAlongsideContentLength(): void
	{
		$body = 'č€ž';

		[, $headers] = $this->flushAndCapture(self::silentPlain($body));

		$this->assertContains('Content-Type: text/plain', $headers);
		$this->assertContains('Content-Length: ' . strlen($body), $headers);
	}

	public function testFlush204DoesNotSetContentLength(): void
	{
		$plain = self::silentPlain('č€ž multibyte body')->statusCode(204);

		$this->flushAndCapture($plain);

		$this->assertFalse($plain->hasHeader('Content-Length'));
		$this->assertSame(204, $plain->getStatusCode());
	}

	public function testFlush1xxDoesNotSetContentLength(): void
	{
		$plain = self::silentPlain('č€ž multibyte body')->statusCode(100);

		$this->flushAndCapture($plain);

		$this->assertFalse($plain->hasHeader('Content-Length'));
	}

}
