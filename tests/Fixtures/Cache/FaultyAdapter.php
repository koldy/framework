<?php declare(strict_types=1);

namespace Tests\Fixtures\Cache;

use Closure;
use Koldy\Cache\Adapter\AbstractCacheAdapter;
use Koldy\Cache\ConnectionException as CacheConnectionException;
use Koldy\Cache\DataException as CacheDataException;

/**
 * Test-only cache adapter that can be configured to throw on demand.
 *
 * Used by the FailoverProxy test suite to drive every branch of the failover
 * logic without depending on real infrastructure (no filesystem, no network,
 * no sleeping for TTLs). Stores values in an in-memory instance array.
 *
 * Configure via the 'fail_mode' config key at construction:
 *   - null (default) → behaves like a normal in-memory cache
 *   - 'connection'   → throws CacheConnectionException on every operation
 *   - 'data'         → throws CacheDataException on every operation
 *
 * Or toggle dynamically after construction via setFailMode(); the configured
 * mode is captured per-instance, so two adapter configs that share this class
 * remain independent.
 */
class FaultyAdapter extends AbstractCacheAdapter
{

	private string|null $failMode;

	private array $store = [];

	private array $callCounts = [
		'set' => 0,
		'get' => 0,
		'has' => 0,
		'delete' => 0,
		'setMulti' => 0,
		'getMulti' => 0,
		'deleteMulti' => 0,
		'deleteAll' => 0,
		'deleteOld' => 0,
		'increment' => 0,
		'decrement' => 0,
		'getOrSetMulti' => 0,
	];

	public function __construct(array $config)
	{
		parent::__construct($config);
		$this->failMode = $config['fail_mode'] ?? null;
	}

	public function setFailMode(string|null $mode): void
	{
		$this->failMode = $mode;
	}

	public function getFailMode(): string|null
	{
		return $this->failMode;
	}

	public function getCallCount(string $method): int
	{
		return $this->callCounts[$method] ?? 0;
	}

	public function getStore(): array
	{
		return $this->store;
	}

	private function maybeFail(string $context): void
	{
		if ($this->failMode === 'connection') {
			throw new CacheConnectionException("FaultyAdapter: simulated connection failure on {$context}");
		}

		if ($this->failMode === 'data') {
			throw new CacheDataException("FaultyAdapter: simulated data failure on {$context}");
		}
	}

	public function set(string $key, mixed $value, int|null $seconds = null): void
	{
		$this->callCounts['set']++;
		$this->maybeFail("set({$key})");
		$this->store[$key] = $value;
	}

	public function get(string $key): mixed
	{
		$this->callCounts['get']++;
		$this->maybeFail("get({$key})");
		return $this->store[$key] ?? null;
	}

	public function has(string $key): bool
	{
		$this->callCounts['has']++;
		$this->maybeFail("has({$key})");
		return array_key_exists($key, $this->store);
	}

	public function delete(string $key): void
	{
		$this->callCounts['delete']++;
		$this->maybeFail("delete({$key})");
		unset($this->store[$key]);
	}

	public function deleteMulti(array $keys): void
	{
		$this->callCounts['deleteMulti']++;
		$this->maybeFail('deleteMulti');
		foreach ($keys as $key) {
			unset($this->store[$key]);
		}
	}

	public function deleteAll(): void
	{
		$this->callCounts['deleteAll']++;
		$this->maybeFail('deleteAll');
		$this->store = [];
	}

	public function deleteOld(int|null $olderThanSeconds = null): void
	{
		$this->callCounts['deleteOld']++;
		$this->maybeFail('deleteOld');
	}

	public function getMulti(array $keys): array
	{
		$this->callCounts['getMulti']++;
		$this->maybeFail('getMulti');

		$result = [];
		foreach ($keys as $key) {
			$result[$key] = $this->store[$key] ?? null;
		}
		return $result;
	}

	public function setMulti(array $keyValuePairs, int|null $seconds = null): void
	{
		$this->callCounts['setMulti']++;
		$this->maybeFail('setMulti');

		foreach ($keyValuePairs as $key => $value) {
			$this->store[$key] = $value;
		}
	}

	public function getOrSetMulti(array $keys, Closure $functionOnMissingKeys, int|null $seconds = null): array
	{
		$this->callCounts['getOrSetMulti']++;
		$this->maybeFail('getOrSetMulti');

		$result = [];
		foreach ($keys as $key) {
			$result[$key] = $this->store[$key] ?? null;
		}
		return $result;
	}

	public function increment(string $key, int $howMuch = 1): int
	{
		$this->callCounts['increment']++;
		$this->maybeFail("increment({$key})");

		$current = (int)($this->store[$key] ?? 0);
		$this->store[$key] = $current + $howMuch;
		return $this->store[$key];
	}

	public function decrement(string $key, int $howMuch = 1): int
	{
		$this->callCounts['decrement']++;
		$this->maybeFail("decrement({$key})");

		$current = (int)($this->store[$key] ?? 0);
		$this->store[$key] = $current - $howMuch;
		return $this->store[$key];
	}

}
