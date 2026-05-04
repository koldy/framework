<?php

declare(strict_types=1);

namespace Tests\Cache;

use Koldy\Application;
use Koldy\Cache;
use Koldy\Cache\Adapter\Runtime;
use Koldy\Cache\ConnectionException as CacheConnectionException;
use Koldy\Cache\DataException as CacheDataException;
use Koldy\Cache\FailoverProxy;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tests\Fixtures\Cache\FaultyAdapter;

class FailoverProxyTest extends TestCase
{

	private static bool $appInitialized = false;

	public static function setUpBeforeClass(): void
	{
		if (!self::$appInitialized) {
			$_SERVER['SCRIPT_FILENAME'] = __FILE__;

			Application::useConfig([
				'site_url' => 'http://localhost',
				'env' => Application::TEST,
				'key' => 'FailoverProxyTestKey1234',
				'timezone' => 'UTC',
				'paths' => [
					'application' => __DIR__ . '/',
					'storage' => __DIR__ . '/',
				],
				'configs' => [
					'cache' => [
						// faulty_a → runtime_a: the canonical pair every test exercises
						'faulty_a' => [
							'enabled' => true,
							'adapter_class' => FaultyAdapter::class,
							'options' => [],
							'on_fail' => ['next_adapter' => 'runtime_a', 'ttl' => 30],
						],
						'runtime_a' => [
							'enabled' => true,
							'adapter_class' => Runtime::class,
							'options' => [],
						],

						// chain that exhausts both adapters with connection failures
						'both_fail_primary' => [
							'enabled' => true,
							'adapter_class' => FaultyAdapter::class,
							'options' => ['fail_mode' => 'connection'],
							'on_fail' => ['next_adapter' => 'both_fail_secondary', 'ttl' => 30],
						],
						'both_fail_secondary' => [
							'enabled' => true,
							'adapter_class' => FaultyAdapter::class,
							'options' => ['fail_mode' => 'connection'],
						],

						// cycle: A → B → A, both wired to fail
						'cycle_a' => [
							'enabled' => true,
							'adapter_class' => FaultyAdapter::class,
							'options' => ['fail_mode' => 'connection'],
							'on_fail' => ['next_adapter' => 'cycle_b', 'ttl' => 30],
						],
						'cycle_b' => [
							'enabled' => true,
							'adapter_class' => FaultyAdapter::class,
							'options' => ['fail_mode' => 'connection'],
							'on_fail' => ['next_adapter' => 'cycle_a', 'ttl' => 30],
						],

						// disabled: must NOT get wrapped in a proxy even if on_fail is set
						'disabled_with_failover' => [
							'enabled' => false,
							'adapter_class' => FaultyAdapter::class,
							'options' => [],
							'on_fail' => ['next_adapter' => 'runtime_a', 'ttl' => 30],
						],

						// no failover: bare adapter, used to verify non-wrapping path
						'plain_runtime' => [
							'enabled' => true,
							'adapter_class' => Runtime::class,
							'options' => [],
						],
					],
				],
			]);

			self::$appInitialized = true;
		}
	}

	protected function setUp(): void
	{
		// Wipe any cached adapter instances and failure marks so each test
		// starts from a clean slate. Both properties are protected static, so
		// reflection is the only way to reset them without exposing setters
		// in the production API.
		(new ReflectionProperty(Cache::class, 'adapters'))->setValue(null, []);
		(new ReflectionProperty(Cache::class, 'failures'))->setValue(null, []);

		// Drop any cache-config cached by Application so the suite plays nice
		// with sibling test classes that use a different cache config but
		// share the same PHP process.
		Application::removeConfig('cache');
	}

	private function primary(string $name): FaultyAdapter
	{
		$raw = Cache::getRawAdapter($name);
		$this->assertInstanceOf(FaultyAdapter::class, $raw);
		return $raw;
	}

	// ────────── wrapping & unwrapping ──────────

	public function testAdapterWithOnFailIsWrappedInProxy(): void
	{
		$this->assertInstanceOf(FailoverProxy::class, Cache::getAdapter('faulty_a'));
	}

	public function testAdapterWithoutOnFailIsNotWrapped(): void
	{
		$adapter = Cache::getAdapter('plain_runtime');
		$this->assertNotInstanceOf(FailoverProxy::class, $adapter);
		$this->assertInstanceOf(Runtime::class, $adapter);
	}

	public function testGetRawAdapterUnwrapsProxy(): void
	{
		$this->assertInstanceOf(FaultyAdapter::class, Cache::getRawAdapter('faulty_a'));
	}

	public function testGetRawAdapterReturnsBareAdapterWhenNotWrapped(): void
	{
		$this->assertInstanceOf(Runtime::class, Cache::getRawAdapter('plain_runtime'));
	}

	public function testWrappedAdapterReportsEnabled(): void
	{
		$this->assertTrue(Cache::isEnabled('faulty_a'));
	}

	public function testDisabledAdapterIsNotWrappedEvenWithOnFail(): void
	{
		$adapter = Cache::getAdapter('disabled_with_failover');
		$this->assertNotInstanceOf(FailoverProxy::class, $adapter);
		$this->assertFalse(Cache::isEnabled('disabled_with_failover'));
	}

	// ────────── happy path ──────────

	public function testPrimarySucceedsNoFailoverNoMark(): void
	{
		Cache::getAdapter('faulty_a')->set('foo', 'bar');

		$primary = $this->primary('faulty_a');
		$this->assertSame(1, $primary->getCallCount('set'));
		$this->assertSame(['foo' => 'bar'], $primary->getStore());
		$this->assertFalse(Cache::isFailed('faulty_a'));
	}

	public function testGetReturnsValueFromPrimary(): void
	{
		Cache::getAdapter('faulty_a')->set('foo', 'bar');
		$this->assertSame('bar', Cache::getAdapter('faulty_a')->get('foo'));

		// fallback never received the value
		$this->assertNull(Cache::getRawAdapter('runtime_a')->get('foo'));
	}

	// ────────── ConnectionException triggers failover ──────────

	public function testConnectionFailureRoutesSetToFallback(): void
	{
		$primary = $this->primary('faulty_a');
		$primary->setFailMode('connection');

		Cache::getAdapter('faulty_a')->set('foo', 'bar');

		$this->assertSame(1, $primary->getCallCount('set'), 'primary attempted exactly once');
		$this->assertTrue(Cache::isFailed('faulty_a'), 'primary marked failed after connection error');
		$this->assertSame('bar', Cache::getRawAdapter('runtime_a')->get('foo'), 'value landed on fallback');
	}

	public function testConnectionFailureRoutesGetToFallback(): void
	{
		Cache::getRawAdapter('runtime_a')->set('foo', 'from-fallback');

		$primary = $this->primary('faulty_a');
		$primary->setFailMode('connection');

		$this->assertSame('from-fallback', Cache::getAdapter('faulty_a')->get('foo'));
		$this->assertTrue(Cache::isFailed('faulty_a'));
	}

	public function testConnectionFailureRoutesHasToFallback(): void
	{
		Cache::getRawAdapter('runtime_a')->set('foo', 'value');

		$primary = $this->primary('faulty_a');
		$primary->setFailMode('connection');

		$this->assertTrue(Cache::getAdapter('faulty_a')->has('foo'));
		$this->assertTrue(Cache::isFailed('faulty_a'));
	}

	public function testConnectionFailureRoutesDeleteToFallback(): void
	{
		$fallback = Cache::getRawAdapter('runtime_a');
		$fallback->set('foo', 'value');

		$primary = $this->primary('faulty_a');
		$primary->setFailMode('connection');

		Cache::getAdapter('faulty_a')->delete('foo');

		$this->assertFalse($fallback->has('foo'), 'delete reached the fallback');
		$this->assertTrue(Cache::isFailed('faulty_a'));
	}

	public function testConnectionFailureRoutesIncrementToFallback(): void
	{
		// Pre-seed the fallback so we observe a real increment, not the
		// AbstractCacheAdapter default behaviour of writing 1 for a missing key.
		$fallback = Cache::getRawAdapter('runtime_a');
		$fallback->set('counter', 10);

		$primary = $this->primary('faulty_a');
		$primary->setFailMode('connection');

		$result = Cache::getAdapter('faulty_a')->increment('counter', 5);

		$this->assertSame(15, $result);
		$this->assertSame(15, $fallback->get('counter'));
	}

	// ────────── failure mark short-circuits primary within TTL ──────────

	public function testWithinTtlSecondCallSkipsPrimaryEntirely(): void
	{
		$primary = $this->primary('faulty_a');
		$primary->setFailMode('connection');

		Cache::getAdapter('faulty_a')->set('foo', 'first');
		$this->assertSame(1, $primary->getCallCount('set'));

		// "Heal" the primary; the mark should still keep traffic on the fallback
		// until either the TTL elapses or someone calls Cache::clearFailed().
		$primary->setFailMode(null);

		Cache::getAdapter('faulty_a')->set('bar', 'second');
		$this->assertSame(1, $primary->getCallCount('set'),
			'primary must not be retried while marked failed');

		$fallback = Cache::getRawAdapter('runtime_a');
		$this->assertSame('first', $fallback->get('foo'));
		$this->assertSame('second', $fallback->get('bar'));
	}

	public function testAfterClearFailedPrimaryIsRetried(): void
	{
		$primary = $this->primary('faulty_a');
		$primary->setFailMode('connection');

		Cache::getAdapter('faulty_a')->set('foo', 'a');
		$this->assertSame(1, $primary->getCallCount('set'));
		$this->assertTrue(Cache::isFailed('faulty_a'));

		// Simulate TTL expiry by clearing the mark and healing the primary.
		Cache::clearFailed('faulty_a');
		$primary->setFailMode(null);

		Cache::getAdapter('faulty_a')->set('bar', 'b');
		$this->assertSame(2, $primary->getCallCount('set'),
			'primary should be tried again after the mark is cleared');
		$this->assertFalse(Cache::isFailed('faulty_a'));
		$this->assertArrayHasKey('bar', $primary->getStore());
	}

	public function testExpiredMarkAutoClearsOnIsFailed(): void
	{
		Cache::markFailed('some_adapter', 60);
		$this->assertTrue(Cache::isFailed('some_adapter'));

		// Rewind the 'until' timestamp to the past via reflection.
		$failuresProp = new ReflectionProperty(Cache::class, 'failures');
		$current = $failuresProp->getValue();
		$current['some_adapter']['until'] = time() - 1;
		$failuresProp->setValue(null, $current);

		$this->assertFalse(Cache::isFailed('some_adapter'),
			'isFailed() should auto-clear an expired mark');
	}

	// ────────── DataException does NOT trigger failover ──────────

	public function testDataExceptionPropagatesAndDoesNotMarkFailed(): void
	{
		$primary = $this->primary('faulty_a');
		$primary->setFailMode('data');

		$thrown = null;
		try {
			Cache::getAdapter('faulty_a')->set('foo', 'bar');
		} catch (CacheDataException $e) {
			$thrown = $e;
		}

		$this->assertInstanceOf(CacheDataException::class, $thrown,
			'CacheDataException must propagate to the caller');
		$this->assertFalse(Cache::isFailed('faulty_a'),
			'data errors must not mark the primary failed');
		$this->assertSame(1, $primary->getCallCount('set'),
			'primary attempted once; no retry');
		$this->assertNull(Cache::getRawAdapter('runtime_a')->get('foo'),
			'fallback must not have been touched');
	}

	// ────────── chain exhaustion ──────────

	public function testBothAdaptersFailExceptionBubbles(): void
	{
		$this->expectException(CacheConnectionException::class);
		Cache::getAdapter('both_fail_primary')->set('foo', 'bar');
	}

	public function testBothAdaptersFailMarksPrimary(): void
	{
		try {
			Cache::getAdapter('both_fail_primary')->set('foo', 'bar');
			$this->fail('expected CacheConnectionException');
		} catch (CacheConnectionException) {
			// expected
		}

		$this->assertTrue(Cache::isFailed('both_fail_primary'),
			'primary is marked failed even when the entire chain exhausts');
	}

	// ────────── cycle detection ──────────

	public function testCycleTerminatesWithConnectionException(): void
	{
		$this->expectException(CacheConnectionException::class);
		$this->expectExceptionMessageMatches('/cycled back/i');

		Cache::getAdapter('cycle_a')->set('foo', 'bar');
	}

	public function testCycleMarksAdaptersFailedAlongTheWay(): void
	{
		try {
			Cache::getAdapter('cycle_a')->set('foo', 'bar');
		} catch (CacheConnectionException) {
			// expected — both adapters threw, cycle was detected on the third hop
		}

		$this->assertTrue(Cache::isFailed('cycle_a'),
			'cycle_a is marked after its primary throws');
		$this->assertTrue(Cache::isFailed('cycle_b'),
			'cycle_b is marked after its primary throws');
	}

	public function testResolutionStackIsClearedAfterCycleException(): void
	{
		// Trigger a cycle exception
		try {
			Cache::getAdapter('cycle_a')->set('foo', 'bar');
		} catch (CacheConnectionException) {
		}

		// Clear failure marks so a subsequent call would attempt primaries again
		Cache::clearFailed('cycle_a');
		Cache::clearFailed('cycle_b');

		// Heal one side so a second call can succeed
		$this->primary('cycle_a')->setFailMode(null);

		// If the resolution stack hadn't been cleared by the prior call's
		// finally block, this would immediately throw "cycled back" again.
		Cache::getAdapter('cycle_a')->set('baz', 'qux');

		$this->assertSame(['baz' => 'qux'], $this->primary('cycle_a')->getStore());
	}

	// ────────── failure registry helpers ──────────

	public function testMarkFailedSetsFlag(): void
	{
		Cache::markFailed('some_adapter', 60);
		$this->assertTrue(Cache::isFailed('some_adapter'));
	}

	public function testClearFailedRemovesMark(): void
	{
		Cache::markFailed('some_adapter', 60);
		Cache::clearFailed('some_adapter');
		$this->assertFalse(Cache::isFailed('some_adapter'));
	}

	public function testIsFailedFalseForUnknownAdapter(): void
	{
		$this->assertFalse(Cache::isFailed('never_seen'));
	}

	public function testMarkFailedStoresCause(): void
	{
		$cause = new CacheConnectionException('stub cause');
		Cache::markFailed('some_adapter', 60, $cause);

		$failuresProp = new ReflectionProperty(Cache::class, 'failures');
		$failures = $failuresProp->getValue();

		$this->assertSame($cause, $failures['some_adapter']['cause']);
	}

}
