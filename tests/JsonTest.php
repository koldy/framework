<?php

declare(strict_types=1);

namespace Tests;

use Koldy\Json;
use Koldy\Json\Exception;
use PHPUnit\Framework\TestCase;
use stdClass;

class JsonTest extends TestCase
{

	// ── encode ──

	public function testEncodeArray(): void
	{
		$result = Json::encode(['name' => 'John', 'age' => 30]);
		$this->assertSame('{"name":"John","age":30}', $result);
	}

	public function testEncodeIndexedArray(): void
	{
		$result = Json::encode([1, 2, 3]);
		$this->assertSame('[1,2,3]', $result);
	}

	public function testEncodeEmptyArray(): void
	{
		$result = Json::encode([]);
		$this->assertSame('[]', $result);
	}

	public function testEncodeNestedArray(): void
	{
		$data = ['user' => ['name' => 'John', 'tags' => ['admin', 'user']]];
		$result = Json::encode($data);
		$decoded = json_decode($result, true);
		$this->assertSame($data, $decoded);
	}

	public function testEncodeStdClass(): void
	{
		$obj = new stdClass();
		$obj->name = 'John';
		$obj->age = 30;
		$result = Json::encode($obj);
		$this->assertSame('{"name":"John","age":30}', $result);
	}

	public function testEncodeWithFlags(): void
	{
		$result = Json::encode(['name' => 'John'], JSON_PRETTY_PRINT);
		$this->assertStringContainsString("\n", $result);
	}

	public function testEncodePrimitiveStringThrows(): void
	{
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('primitive value');
		Json::encode('just a string');
	}

	public function testEncodePrimitiveIntThrows(): void
	{
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('primitive value');
		Json::encode(42);
	}

	public function testEncodePrimitiveBoolThrows(): void
	{
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('primitive value');
		Json::encode(true);
	}

	public function testEncodePrimitiveNullThrows(): void
	{
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('primitive value');
		Json::encode(null);
	}

	public function testEncodeWithDepthLimit(): void
	{
		// Deeply nested structure that exceeds depth=2
		$data = ['a' => ['b' => ['c' => 'd']]];
		$this->expectException(Exception::class);
		Json::encode($data, 0, 2);
	}

	// ── decode ──

	public function testDecodeObject(): void
	{
		$result = Json::decode('{"name":"John","age":30}');
		$this->assertSame(['name' => 'John', 'age' => 30], $result);
	}

	public function testDecodeArray(): void
	{
		$result = Json::decode('[1,2,3]');
		$this->assertSame([1, 2, 3], $result);
	}

	public function testDecodeEmptyObject(): void
	{
		$result = Json::decode('{}');
		$this->assertSame([], $result);
	}

	public function testDecodeEmptyArray(): void
	{
		$result = Json::decode('[]');
		$this->assertSame([], $result);
	}

	public function testDecodeNestedJson(): void
	{
		$json = '{"user":{"name":"John","tags":["admin","user"]}}';
		$result = Json::decode($json);
		$this->assertSame('John', $result['user']['name']);
		$this->assertSame(['admin', 'user'], $result['user']['tags']);
	}

	public function testDecodeInvalidJsonThrows(): void
	{
		$this->expectException(Exception::class);
		Json::decode('not valid json');
	}

	public function testDecodeEmptyStringThrows(): void
	{
		$this->expectException(Exception::class);
		Json::decode('');
	}

	// ── decodeToObj ──

	public function testDecodeToObjReturnsStdClass(): void
	{
		$result = Json::decodeToObj('{"name":"John","age":30}');
		$this->assertInstanceOf(stdClass::class, $result);
		$this->assertSame('John', $result->name);
		$this->assertSame(30, $result->age);
	}

	public function testDecodeToObjNestedObjects(): void
	{
		$result = Json::decodeToObj('{"user":{"name":"John"}}');
		$this->assertInstanceOf(stdClass::class, $result->user);
		$this->assertSame('John', $result->user->name);
	}

	public function testDecodeToObjInvalidJsonThrows(): void
	{
		$this->expectException(Exception::class);
		Json::decodeToObj('invalid');
	}

	public function testDecodeToObjEmptyStringThrows(): void
	{
		$this->expectException(Exception::class);
		Json::decodeToObj('');
	}

	// ── roundtrip ──

	public function testEncodeDecodeRoundtrip(): void
	{
		$data = ['name' => 'John', 'scores' => [100, 95, 88], 'active' => true];
		$json = Json::encode($data);
		$decoded = Json::decode($json);
		$this->assertSame($data, $decoded);
	}

}

