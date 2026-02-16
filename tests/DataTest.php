<?php

declare(strict_types=1);

namespace Tests;

use Koldy\Data;
use PHPUnit\Framework\TestCase;

/**
 * Concrete class that uses the Data trait so we can test it
 */
class DataTestSubject
{
	use Data;
}

class DataTest extends TestCase
{

	// ── getData / setData ──

	public function testGetDataReturnsEmptyArrayByDefault(): void
	{
		$obj = new DataTestSubject();
		$this->assertSame([], $obj->getData());
	}

	public function testSetDataOverridesAllData(): void
	{
		$obj = new DataTestSubject();
		$obj->setData(['a' => 1, 'b' => 2]);
		$this->assertSame(['a' => 1, 'b' => 2], $obj->getData());
	}

	public function testSetDataReturnsSelf(): void
	{
		$obj = new DataTestSubject();
		$result = $obj->setData(['x' => 1]);
		$this->assertSame($obj, $result);
	}

	public function testSetDataOverridesPreviousData(): void
	{
		$obj = new DataTestSubject();
		$obj->setData(['a' => 1]);
		$obj->setData(['b' => 2]);
		$this->assertSame(['b' => 2], $obj->getData());
	}

	// ── addData ──

	public function testAddDataMergesWithExisting(): void
	{
		$obj = new DataTestSubject();
		$obj->setData(['a' => 1]);
		$obj->addData(['b' => 2]);
		$this->assertSame(['a' => 1, 'b' => 2], $obj->getData());
	}

	public function testAddDataOverridesExistingKeys(): void
	{
		$obj = new DataTestSubject();
		$obj->setData(['a' => 1, 'b' => 2]);
		$obj->addData(['b' => 99, 'c' => 3]);
		$this->assertSame(['a' => 1, 'b' => 99, 'c' => 3], $obj->getData());
	}

	public function testAddDataReturnsSelf(): void
	{
		$obj = new DataTestSubject();
		$result = $obj->addData(['x' => 1]);
		$this->assertSame($obj, $result);
	}

	// ── set / get ──

	public function testSetAndGet(): void
	{
		$obj = new DataTestSubject();
		$obj->set('name', 'John');
		$this->assertSame('John', $obj->get('name'));
	}

	public function testGetReturnsNullForMissingKey(): void
	{
		$obj = new DataTestSubject();
		$this->assertNull($obj->get('nonexistent'));
	}

	public function testSetReturnsSelf(): void
	{
		$obj = new DataTestSubject();
		$result = $obj->set('key', 'value');
		$this->assertSame($obj, $result);
	}

	public function testSetOverridesExistingKey(): void
	{
		$obj = new DataTestSubject();
		$obj->set('key', 'first');
		$obj->set('key', 'second');
		$this->assertSame('second', $obj->get('key'));
	}

	public function testSetAcceptsMixedValues(): void
	{
		$obj = new DataTestSubject();
		$obj->set('int', 42);
		$obj->set('bool', true);
		$obj->set('array', [1, 2, 3]);
		$obj->set('null', null);

		$this->assertSame(42, $obj->get('int'));
		$this->assertTrue($obj->get('bool'));
		$this->assertSame([1, 2, 3], $obj->get('array'));
		$this->assertNull($obj->get('null'));
	}

	// ── __get / __set (magic methods) ──

	public function testMagicSetAndGet(): void
	{
		$obj = new DataTestSubject();
		$obj->name = 'Jane';
		$this->assertSame('Jane', $obj->name);
	}

	public function testMagicGetReturnsNullForMissingKey(): void
	{
		$obj = new DataTestSubject();
		$this->assertNull($obj->nonexistent);
	}

	// ── has ──

	public function testHasReturnsTrueForExistingKey(): void
	{
		$obj = new DataTestSubject();
		$obj->set('key', 'value');
		$this->assertTrue($obj->has('key'));
	}

	public function testHasReturnsFalseForMissingKey(): void
	{
		$obj = new DataTestSubject();
		$this->assertFalse($obj->has('missing'));
	}

	public function testHasReturnsTrueForNullValue(): void
	{
		$obj = new DataTestSubject();
		$obj->set('key', null);
		$this->assertTrue($obj->has('key'));
	}

	// ── delete ──

	public function testDeleteRemovesKey(): void
	{
		$obj = new DataTestSubject();
		$obj->set('key', 'value');
		$obj->delete('key');
		$this->assertFalse($obj->has('key'));
		$this->assertNull($obj->get('key'));
	}

	public function testDeleteReturnsSelf(): void
	{
		$obj = new DataTestSubject();
		$result = $obj->delete('nonexistent');
		$this->assertSame($obj, $result);
	}

	public function testDeleteNonexistentKeyDoesNotThrow(): void
	{
		$obj = new DataTestSubject();
		$obj->set('a', 1);
		$obj->delete('b');
		$this->assertSame(['a' => 1], $obj->getData());
	}

	// ── deleteAll ──

	public function testDeleteAllClearsAllData(): void
	{
		$obj = new DataTestSubject();
		$obj->setData(['a' => 1, 'b' => 2, 'c' => 3]);
		$obj->deleteAll();
		$this->assertSame([], $obj->getData());
	}

	public function testDeleteAllReturnsSelf(): void
	{
		$obj = new DataTestSubject();
		$result = $obj->deleteAll();
		$this->assertSame($obj, $result);
	}

	public function testDeleteAllOnEmptyData(): void
	{
		$obj = new DataTestSubject();
		$obj->deleteAll();
		$this->assertSame([], $obj->getData());
	}

	// ── fluent interface chaining ──

	public function testFluentChaining(): void
	{
		$obj = new DataTestSubject();
		$result = $obj
			->set('a', 1)
			->set('b', 2)
			->addData(['c' => 3])
			->delete('b');

		$this->assertSame($obj, $result);
		$this->assertSame(['a' => 1, 'c' => 3], $obj->getData());
	}

}
