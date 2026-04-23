<?php

declare(strict_types=1);

namespace Tests\Response;

use Koldy\Response\Plain;
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

}
