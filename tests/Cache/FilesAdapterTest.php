<?php

declare(strict_types=1);

namespace Tests\Cache;

use Koldy\Application;
use Koldy\Cache;
use Koldy\Cache\Adapter\Files;
use Koldy\Cache\Adapter\Runtime;
use Koldy\Cache\ConnectionException as CacheConnectionException;
use Koldy\Cache\Exception as CacheException;
use Koldy\Cache\FailoverProxy;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Tests for the connection-error classification added to {@see Files}, plus a
 * Files → Runtime end-to-end smoke test that exercises the failover proxy
 * against a real adapter (not just the FaultyAdapter stub).
 *
 * The failure path is triggered by pointing the cache at "/dev/null/...". On
 * Unix, /dev/null is a character device, so PHP's mkdir() always fails when
 * asked to create a child path under it — giving us a deterministic
 * write-failure scenario without race conditions or chmod gymnastics.
 */
class FilesAdapterTest extends TestCase
{

	private static bool $appInitialized = false;

	private string $tmpRoot;

	public static function setUpBeforeClass(): void
	{
		if (!self::$appInitialized) {
			$_SERVER['SCRIPT_FILENAME'] = __FILE__;

			Application::useConfig([
				'site_url' => 'http://localhost',
				'env' => Application::TEST,
				'key' => 'FilesAdapterTestKey12345',
				'timezone' => 'UTC',
				'paths' => [
					'application' => __DIR__ . '/',
					'storage' => __DIR__ . '/',
				],
				'configs' => [
					'cache' => [
						// Files pointed at an unwritable location, with Runtime as fallback
						'fragile_files' => [
							'enabled' => true,
							'adapter_class' => Files::class,
							'options' => ['path' => '/dev/null/cache_test/'],
							'on_fail' => ['next_adapter' => 'runtime_fallback', 'ttl' => 30],
						],
						'runtime_fallback' => [
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
		if (PHP_OS_FAMILY === 'Windows') {
			$this->markTestSkipped('Filesystem failure tests rely on Unix /dev/null semantics');
		}

		// Each test gets its own temp directory under sys_get_temp_dir(), and
		// the *parent* directory we hand to Files is one level deeper so the
		// adapter's auto-mkdir kicks in on first write. We delete the whole
		// tree on tearDown.
		$this->tmpRoot = sys_get_temp_dir() . '/koldy_files_test_' . uniqid('', true);

		(new ReflectionProperty(Cache::class, 'adapters'))->setValue(null, []);
		(new ReflectionProperty(Cache::class, 'failures'))->setValue(null, []);

		// Drop any cache-config cached by Application so the suite plays nice
		// with sibling test classes that use a different cache config but
		// share the same PHP process.
		Application::removeConfig('cache');
	}

	protected function tearDown(): void
	{
		if (isset($this->tmpRoot) && is_dir($this->tmpRoot)) {
			$this->rmrf($this->tmpRoot);
		}
	}

	private function rmrf(string $path): void
	{
		if (!file_exists($path)) {
			return;
		}

		if (is_file($path) || is_link($path)) {
			@unlink($path);
			return;
		}

		foreach (scandir($path) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}
			$this->rmrf($path . '/' . $entry);
		}

		@rmdir($path);
	}

	// ────────── happy path: writable directory ──────────

	public function testHappyPathWriteAndRead(): void
	{
		$adapter = new Files(['path' => $this->tmpRoot . '/cache/']);

		$adapter->set('foo', 'bar');
		$this->assertSame('bar', $adapter->get('foo'));
		$this->assertTrue($adapter->has('foo'));
	}

	public function testHasReturnsFalseForUnknownKey(): void
	{
		$adapter = new Files(['path' => $this->tmpRoot . '/cache/']);

		$this->assertFalse($adapter->has('never_set'));
	}

	public function testGetReturnsNullForUnknownKey(): void
	{
		$adapter = new Files(['path' => $this->tmpRoot . '/cache/']);

		$this->assertNull($adapter->get('never_set'));
	}

	public function testDeleteOfUnknownKeyIsNoop(): void
	{
		$adapter = new Files(['path' => $this->tmpRoot . '/cache/']);

		// Should not throw — deleting a non-existent key is benign
		$adapter->delete('never_set');
		$this->assertFalse($adapter->has('never_set'));
	}

	// ────────── set() failures ──────────

	public function testSetThrowsConnectionExceptionWhenDirectoryCannotBeCreated(): void
	{
		// /dev/null is a character device; mkdir under it is always refused.
		$adapter = new Files(['path' => '/dev/null/cache_test/']);

		try {
			$adapter->set('foo', 'bar');
			$this->fail('expected CacheConnectionException');
		} catch (CacheConnectionException $e) {
			$this->assertStringContainsString('/dev/null', $e->getMessage(),
				'message should identify the offending path');
			$this->assertMatchesRegularExpression(
				'/(could not be created|Permission denied|Not a directory|File exists|mkdir)/i',
				$e->getMessage(),
				'message should include the underlying mkdir reason'
			);
		}
	}

	public function testConnectionExceptionExtendsCacheException(): void
	{
		// Verifies the inheritance contract that lets existing callers keep
		// catching the base CacheException unchanged.
		$adapter = new Files(['path' => '/dev/null/cache_test/']);

		$this->expectException(CacheException::class);
		$adapter->set('foo', 'bar');
	}

	public function testSetIdentifiesTheCacheKeyInTheMessage(): void
	{
		$adapter = new Files(['path' => '/dev/null/cache_test/']);

		try {
			$adapter->set('my_specific_key', 'value');
			$this->fail('expected CacheConnectionException');
		} catch (CacheConnectionException $e) {
			$this->assertStringContainsString('my_specific_key', $e->getMessage());
		}
	}

	// ────────── delete() classification ──────────

	public function testDeleteOfNonExistentKeyDoesNotThrow(): void
	{
		$adapter = new Files(['path' => $this->tmpRoot . '/cache/']);

		// Pre-create the directory so the constructor doesn't auto-mkdir
		mkdir($this->tmpRoot . '/cache', 0755, true);

		// Deleting a key that was never set should be silently ok
		$adapter->delete('not_there');
		$this->assertTrue(true, 'no exception thrown');
	}

	public function testDeleteThrowsConnectionExceptionWhenUnlinkFails(): void
	{
		// Set up a real file, then make its parent directory non-writable so
		// the unlink fails with EACCES.
		$cacheDir = $this->tmpRoot . '/cache/';
		$adapter = new Files(['path' => $cacheDir]);

		$adapter->set('victim', 'value');
		$this->assertTrue($adapter->has('victim'), 'precondition: file exists');

		// Read-only the parent directory so unlink can't remove entries
		chmod($cacheDir, 0555);

		try {
			$adapter->delete('victim');
			$this->fail('expected CacheConnectionException');
		} catch (CacheConnectionException $e) {
			$this->assertStringContainsString('victim', $e->getMessage());
			$this->assertMatchesRegularExpression(
				'/(Permission denied|Read-only|unlink)/i',
				$e->getMessage()
			);
		} finally {
			// Restore permissions so tearDown can clean up
			chmod($cacheDir, 0755);
		}
	}

	// ────────── load() / has() classification ──────────

	public function testHasPropagatesConnectionExceptionOnUnreadableFile(): void
	{
		$cacheDir = $this->tmpRoot . '/cache/';
		$adapter = new Files(['path' => $cacheDir]);
		$adapter->set('victim', 'value');

		// chmod the file to 000 to force a read failure
		$path = $cacheDir . 'victim.txt';
		chmod($path, 0000);

		// Open a fresh adapter so no in-memory copy of the data exists
		$reader = new Files(['path' => $cacheDir]);

		try {
			$reader->has('victim');
			$this->fail('expected CacheConnectionException');
		} catch (CacheConnectionException $e) {
			$this->assertStringContainsString('victim', $e->getMessage());
			$this->assertMatchesRegularExpression('/(Permission denied|read)/i', $e->getMessage());
		} finally {
			chmod($path, 0644);
		}
	}

	public function testHasReturnsFalseForCorruptFile(): void
	{
		// A file that can be read but isn't in the framework's expected format
		// is treated as a cache miss, NOT a connection failure — corruption
		// signals a data problem, and falling over to a different cache
		// wouldn't help (it doesn't have the data either).
		$cacheDir = $this->tmpRoot . '/cache/';
		mkdir($cacheDir, 0755, true);

		// Write a malformed cache file (no newline = no header separator)
		file_put_contents($cacheDir . 'bad.txt', 'this is not a valid cache file format');

		$adapter = new Files(['path' => $cacheDir]);

		$this->assertFalse($adapter->has('bad'),
			'corrupt files yield a cache miss, not a connection error');
	}

	// ────────── deleteAll() classification ──────────

	public function testDeleteAllSucceedsOnEmptyDirectory(): void
	{
		$cacheDir = $this->tmpRoot . '/cache/';
		mkdir($cacheDir, 0755, true);

		$adapter = new Files(['path' => $cacheDir]);
		$adapter->deleteAll();

		$this->assertTrue(is_dir($cacheDir), 'directory itself remains');
	}

	public function testDeleteAllRemovesEntries(): void
	{
		$cacheDir = $this->tmpRoot . '/cache/';
		$adapter = new Files(['path' => $cacheDir]);

		$adapter->set('a', '1');
		$adapter->set('b', '2');
		$adapter->set('c', '3');

		$adapter->deleteAll();

		$this->assertFalse($adapter->has('a'));
		$this->assertFalse($adapter->has('b'));
		$this->assertFalse($adapter->has('c'));
	}

	// ────────── end-to-end: Files → Runtime via FailoverProxy ──────────

	public function testFilesFailureFailsOverToRuntime(): void
	{
		// fragile_files points at /dev/null/cache_test/ which can't be created.
		// Calling set() must throw CacheConnectionException internally, the
		// proxy must catch it, and Runtime must end up with the value.
		$adapter = Cache::getAdapter('fragile_files');
		$this->assertInstanceOf(FailoverProxy::class, $adapter);

		$adapter->set('foo', 'bar');

		// Value is on the Runtime fallback
		$this->assertSame('bar', Cache::getRawAdapter('runtime_fallback')->get('foo'));

		// Primary is now marked failed for its TTL
		$this->assertTrue(Cache::isFailed('fragile_files'));
	}

	public function testFilesFailureDoesNotLoseSubsequentWrites(): void
	{
		$adapter = Cache::getAdapter('fragile_files');

		$adapter->set('first', 'one');
		$adapter->set('second', 'two');
		$adapter->set('third', 'three');

		$fallback = Cache::getRawAdapter('runtime_fallback');
		$this->assertSame('one', $fallback->get('first'));
		$this->assertSame('two', $fallback->get('second'));
		$this->assertSame('three', $fallback->get('third'));
	}

	public function testFilesFailureGetReturnsValueFromRuntimeFallback(): void
	{
		// Pre-seed runtime so we can verify the read path
		Cache::getRawAdapter('runtime_fallback')->set('seeded', 'from-runtime');

		$adapter = Cache::getAdapter('fragile_files');

		// Reading a missing key from Files would simply return null (cache
		// miss) without throwing — that's not a connection failure, so the
		// proxy wouldn't trigger failover. We need a write to surface the
		// underlying mkdir failure first; that mark then routes subsequent
		// reads in the same request straight to the fallback.
		$adapter->set('warmup', 'value');
		$this->assertTrue(Cache::isFailed('fragile_files'),
			'precondition: the failed write marked the primary');

		$this->assertSame('from-runtime', $adapter->get('seeded'));
	}

}
