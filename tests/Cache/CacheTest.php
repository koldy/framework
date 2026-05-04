<?php

declare(strict_types=1);

namespace Tests\Cache;

use Koldy\Application;
use Koldy\Cache;
use Koldy\Cache\Adapter\DevNull;
use Koldy\Cache\Adapter\Runtime;
use Koldy\Config\Exception as ConfigException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Tests for the Cache static facade (src/Cache.php).
 *
 * Covers: adapter resolution, buildAdapter() config validation,
 * wrapInFailoverProxy() config validation, isEnabled(), hasAdapter(),
 * and all CRUD static-facade methods (get/set/has/delete/getMulti/
 * setMulti/deleteMulti/getOrSet/increment/decrement).
 *
 * All adapters used here are Runtime (in-memory) or DevNull — no
 * external infrastructure required.
 */
class CacheTest extends TestCase
{

	private static bool $appInitialized = false;

	public static function setUpBeforeClass(): void
	{
		if (!self::$appInitialized) {
			$_SERVER['SCRIPT_FILENAME'] = __FILE__;

			Application::useConfig([
				'site_url' => 'http://localhost',
				'env' => Application::TEST,
				'key' => 'CacheTestKey1234567890AB',
				'timezone' => 'UTC',
				'paths' => [
					'application' => __DIR__ . '/',
					'storage' => __DIR__ . '/',
				],
				'configs' => [
					'cache' => [
						// Default adapter (first key) — used by all static-facade tests
						'main' => [
							'enabled' => true,
							'adapter_class' => Runtime::class,
							'options' => [],
						],
						// Second adapter — for hasAdapter() lifecycle tests
						'secondary' => [
							'enabled' => true,
							'adapter_class' => Runtime::class,
							'options' => [],
						],
						// disabled → DevNull
						'disabled' => [
							'enabled' => false,
							'adapter_class' => Runtime::class,
							'options' => [],
						],
						// config-error: missing adapter_class
						'no_class' => [
							'enabled' => true,
						],
						// config-error: non-existent class name
						'bad_class' => [
							'enabled' => true,
							'adapter_class' => 'NonExistent\\CacheAdapterClass',
						],
						// config-error: on_fail value is not an array
						'bad_onfail_type' => [
							'enabled' => true,
							'adapter_class' => Runtime::class,
							'options' => [],
							'on_fail' => 'not-an-array',
						],
						// config-error: on_fail has no next_adapter key
						'bad_onfail_no_next' => [
							'enabled' => true,
							'adapter_class' => Runtime::class,
							'options' => [],
							'on_fail' => ['ttl' => 30],
						],
						// config-error: on_fail.next_adapter == own key (self-reference)
						'bad_onfail_self' => [
							'enabled' => true,
							'adapter_class' => Runtime::class,
							'options' => [],
							'on_fail' => ['next_adapter' => 'bad_onfail_self', 'ttl' => 30],
						],
						// config-error: on_fail.next_adapter names an adapter not in config
						'bad_onfail_missing' => [
							'enabled' => true,
							'adapter_class' => Runtime::class,
							'options' => [],
							'on_fail' => ['next_adapter' => 'does_not_exist', 'ttl' => 30],
						],
						// config-error: on_fail has no ttl
						'bad_onfail_no_ttl' => [
							'enabled' => true,
							'adapter_class' => Runtime::class,
							'options' => [],
							'on_fail' => ['next_adapter' => 'main'],
						],
						// config-error: on_fail.ttl is 0 (must be >= 1)
						'bad_onfail_zero_ttl' => [
							'enabled' => true,
							'adapter_class' => Runtime::class,
							'options' => [],
							'on_fail' => ['next_adapter' => 'main', 'ttl' => 0],
						],
					],
				],
			]);

			self::$appInitialized = true;
		}
	}

	protected function setUp(): void
	{
		// Reset cached adapter instances and failure marks before each test so
		// tests don't bleed state into each other.
		(new ReflectionProperty(Cache::class, 'adapters'))->setValue(null, []);
		(new ReflectionProperty(Cache::class, 'failures'))->setValue(null, []);
	}

	// ────────── Adapter resolution ──────────

	public function testEnabledAdapterResolvesToRuntime(): void
	{
		$this->assertInstanceOf(Runtime::class, Cache::getAdapter('main'));
	}

	public function testGetAdapterCachesInstance(): void
	{
		$first = Cache::getAdapter('main');
		$second = Cache::getAdapter('main');

		$this->assertSame($first, $second, 'getAdapter() must return the same instance on repeated calls');
	}

	public function testGetAdapterNullUsesFirstKey(): void
	{
		$this->assertSame(Cache::getAdapter('main'), Cache::getAdapter(null));
	}

	public function testDisabledAdapterReturnsDevNull(): void
	{
		$this->assertInstanceOf(DevNull::class, Cache::getAdapter('disabled'));
	}

	public function testMissingAdapterClassThrowsConfigException(): void
	{
		$this->expectException(ConfigException::class);
		Cache::getAdapter('no_class');
	}

	public function testNonExistentAdapterClassThrowsConfigException(): void
	{
		$this->expectException(ConfigException::class);
		Cache::getAdapter('bad_class');
	}

	// ────────── isEnabled / hasAdapter ──────────

	public function testIsEnabledTrueForRuntime(): void
	{
		$this->assertTrue(Cache::isEnabled('main'));
	}

	public function testIsEnabledFalseForDisabledAdapter(): void
	{
		$this->assertFalse(Cache::isEnabled('disabled'));
	}

	public function testHasAdapterFalseBeforeAccess(): void
	{
		$this->assertFalse(Cache::hasAdapter('secondary'),
			'hasAdapter() must return false before the adapter has been resolved');
	}

	public function testHasAdapterTrueAfterGetAdapter(): void
	{
		Cache::getAdapter('secondary');
		$this->assertTrue(Cache::hasAdapter('secondary'));
	}

	// ────────── on_fail config validation ──────────

	public function testOnFailNotArrayThrowsConfigException(): void
	{
		$this->expectException(ConfigException::class);
		Cache::getAdapter('bad_onfail_type');
	}

	public function testOnFailMissingNextAdapterThrowsConfigException(): void
	{
		$this->expectException(ConfigException::class);
		Cache::getAdapter('bad_onfail_no_next');
	}

	public function testOnFailSelfReferenceThrowsConfigException(): void
	{
		$this->expectException(ConfigException::class);
		Cache::getAdapter('bad_onfail_self');
	}

	public function testOnFailMissingNextAdapterConfigThrowsConfigException(): void
	{
		$this->expectException(ConfigException::class);
		Cache::getAdapter('bad_onfail_missing');
	}

	public function testOnFailMissingTtlThrowsConfigException(): void
	{
		$this->expectException(ConfigException::class);
		Cache::getAdapter('bad_onfail_no_ttl');
	}

	public function testOnFailZeroTtlThrowsConfigException(): void
	{
		$this->expectException(ConfigException::class);
		Cache::getAdapter('bad_onfail_zero_ttl');
	}

	// ────────── Static CRUD facade ──────────
	// All methods below rely on 'main' (Runtime) as the default adapter.

	public function testSetAndGet(): void
	{
		Cache::set('hello', 'world');
		$this->assertSame('world', Cache::get('hello'));
	}

	public function testGetReturnsNullForMissingKey(): void
	{
		$this->assertNull(Cache::get('does_not_exist'));
	}

	public function testHasReturnsFalseForMissingKey(): void
	{
		$this->assertFalse(Cache::has('does_not_exist'));
	}

	public function testHasReturnsTrueAfterSet(): void
	{
		Cache::set('present', 'value');
		$this->assertTrue(Cache::has('present'));
	}

	public function testDeleteRemovesKey(): void
	{
		Cache::set('to_delete', 'value');
		Cache::delete('to_delete');
		$this->assertFalse(Cache::has('to_delete'));
	}

	public function testSetMultiAndGetMulti(): void
	{
		Cache::setMulti(['a' => 'alpha', 'b' => 'beta']);
		$result = Cache::getMulti(['a', 'b']);

		$this->assertSame('alpha', $result['a']);
		$this->assertSame('beta', $result['b']);
	}

	public function testGetMultiMissingKeysReturnNull(): void
	{
		$result = Cache::getMulti(['missing_x', 'missing_y']);

		$this->assertNull($result['missing_x']);
		$this->assertNull($result['missing_y']);
	}

	public function testDeleteMultiRemovesKeys(): void
	{
		Cache::setMulti(['x' => 1, 'y' => 2, 'z' => 3]);
		Cache::deleteMulti(['x', 'y']);

		$this->assertFalse(Cache::has('x'));
		$this->assertFalse(Cache::has('y'));
		$this->assertTrue(Cache::has('z'), 'key not in deleteMulti list must remain');
	}

	public function testGetOrSetCallsClosureOnMiss(): void
	{
		$called = 0;
		$value = Cache::getOrSet('new_key', function () use (&$called) {
			$called++;
			return 'computed';
		});

		$this->assertSame('computed', $value);
		$this->assertSame(1, $called, 'closure must be invoked exactly once on a cache miss');
	}

	public function testGetOrSetReturnsCachedOnHit(): void
	{
		Cache::set('cached_key', 'original');

		$called = 0;
		$value = Cache::getOrSet('cached_key', function () use (&$called) {
			$called++;
			return 'should_not_be_returned';
		});

		$this->assertSame('original', $value);
		$this->assertSame(0, $called, 'closure must not be called when the key already exists');
	}

	public function testIncrementOnMissingKeyReturnsOne(): void
	{
		$result = Cache::increment('counter_new');
		$this->assertSame(1, $result);
	}

	public function testIncrementAddsToExistingValue(): void
	{
		Cache::set('counter_existing', 5);
		$result = Cache::increment('counter_existing', 3);
		$this->assertSame(8, $result);
	}

	public function testDecrementOnMissingKeyReturnsNegativeOne(): void
	{
		$result = Cache::decrement('decrement_new');
		$this->assertSame(-1, $result);
	}

	public function testDecrementSubtractsFromExistingValue(): void
	{
		Cache::set('decrement_existing', 10);
		$result = Cache::decrement('decrement_existing', 4);
		$this->assertSame(6, $result);
	}

}
