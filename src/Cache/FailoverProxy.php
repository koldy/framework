<?php declare(strict_types=1);

namespace Koldy\Cache;

use Closure;
use Koldy\Cache;
use Koldy\Cache\Adapter\AbstractCacheAdapter;
use Koldy\Cache\ConnectionException as CacheConnectionException;
use Koldy\Log;

/**
 * Internal proxy that wraps a "primary" cache adapter and falls over to the
 * next adapter in the chain when the primary throws CacheConnectionException.
 *
 * Constructed automatically by {@see Cache::getAdapter()} when a cache config
 * entry declares an "on_fail" block. Users do not instantiate this class
 * directly and must never reference it from configuration.
 *
 * Failure state lives in {@see Cache::$failures} and is per-request. Once the
 * primary is marked failed, subsequent calls in the same request short-circuit
 * straight to the next adapter for `ttl` seconds counted from the moment the
 * failure was recorded. After the TTL elapses the primary is retried; if it
 * fails again, the mark is renewed.
 *
 * Cycles in the configured chain (A → B → A) are detected via a per-call
 * resolution stack: the second time we'd enter the same proxy in a single
 * call the chain is declared exhausted and a CacheConnectionException is
 * thrown. Without this, two adapters that loop back to each other would
 * recurse forever once both were marked failed (each proxy would skip its
 * primary and immediately delegate to the other).
 */
class FailoverProxy extends AbstractCacheAdapter
{

	/**
	 * Names of proxies currently mid-call. Used by execute() to break cycles.
	 * Class-scoped (not instance-scoped) so peer proxies on the same chain
	 * share the same view of which names are already on the stack. Cleared
	 * by a finally block on exit, so a thrown exception from anywhere in the
	 * chain still leaves the stack empty for the next request.
	 *
	 * @var array<string, true>
	 */
	private static array $resolutionStack = [];

	public function __construct(
		private readonly string $primaryName,
		private readonly AbstractCacheAdapter $primary,
		private readonly string $nextName,
		private readonly int $ttl
	) {
		// AbstractCacheAdapter::__construct sets $this->config and registers a
		// shutdown hook for clean_old. The proxy has no config of its own —
		// the wrapped adapters carry their own — so pass an empty array to
		// disable both behaviours on the proxy itself.
		parent::__construct([]);
	}

	/**
	 * Return the wrapped primary adapter, bypassing failover. Useful when
	 * callers need adapter-specific functionality not on AbstractCacheAdapter
	 * (e.g. {@see Memcached::getInstance()}). For a generic bypass that walks
	 * any chain depth, prefer {@see Cache::getRawAdapter()}.
	 */
	public function getPrimary(): AbstractCacheAdapter
	{
		return $this->primary;
	}

	/**
	 * Run the given operation against the primary; on a connection-level
	 * failure mark the primary failed and re-run against the next adapter in
	 * the chain. Any non-connection CacheException (data error, config error)
	 * propagates without triggering failover.
	 *
	 * @param Closure $op receives an AbstractCacheAdapter and returns the
	 *                    operation's result (mixed; void methods return null)
	 *
	 * @return mixed
	 */
	private function execute(Closure $op): mixed
	{
		if (isset(self::$resolutionStack[$this->primaryName])) {
			// We've re-entered the same proxy within a single call — the chain
			// must contain a cycle. Bail loudly rather than recursing forever.
			throw new CacheConnectionException("Cache failover chain cycled back to '{$this->primaryName}'; chain exhausted without a healthy adapter");
		}

		self::$resolutionStack[$this->primaryName] = true;

		try {
			if (!Cache::isFailed($this->primaryName)) {
				try {
					return $op($this->primary);
				} catch (CacheConnectionException $e) {
					Cache::markFailed($this->primaryName, $this->ttl, $e);
					Log::warning("Cache adapter '{$this->primaryName}' marked failed for {$this->ttl}s; falling over to '{$this->nextName}': {$e->getMessage()}");
				}
			}

			return $op(Cache::getAdapter($this->nextName));
		} finally {
			unset(self::$resolutionStack[$this->primaryName]);
		}
	}

	public function set(string $key, mixed $value, int|null $seconds = null): void
	{
		$this->execute(fn(AbstractCacheAdapter $a) => $a->set($key, $value, $seconds));
	}

	public function get(string $key): mixed
	{
		return $this->execute(fn(AbstractCacheAdapter $a) => $a->get($key));
	}

	public function has(string $key): bool
	{
		return $this->execute(fn(AbstractCacheAdapter $a) => $a->has($key));
	}

	public function delete(string $key): void
	{
		$this->execute(fn(AbstractCacheAdapter $a) => $a->delete($key));
	}

	public function deleteMulti(array $keys): void
	{
		$this->execute(fn(AbstractCacheAdapter $a) => $a->deleteMulti($keys));
	}

	public function deleteAll(): void
	{
		$this->execute(fn(AbstractCacheAdapter $a) => $a->deleteAll());
	}

	public function deleteOld(int|null $olderThanSeconds = null): void
	{
		$this->execute(fn(AbstractCacheAdapter $a) => $a->deleteOld($olderThanSeconds));
	}

	public function getMulti(array $keys): array
	{
		return $this->execute(fn(AbstractCacheAdapter $a) => $a->getMulti($keys));
	}

	public function setMulti(array $keyValuePairs, int|null $seconds = null): void
	{
		$this->execute(fn(AbstractCacheAdapter $a) => $a->setMulti($keyValuePairs, $seconds));
	}

	public function getOrSetMulti(array $keys, Closure $functionOnMissingKeys, int|null $seconds = null): array
	{
		// Delegated as one atomic call. If the primary fails partway through
		// (e.g. connection drops mid-set), the closure will be invoked again
		// on the fallback — same semantics as any retried network call. If
		// that's a problem for a side-effecting closure, use Cache::getMulti
		// + Cache::setMulti explicitly instead.
		return $this->execute(fn(AbstractCacheAdapter $a) => $a->getOrSetMulti($keys, $functionOnMissingKeys,
			$seconds));
	}

	/**
	 * Delegated rather than using {@see AbstractCacheAdapter::increment}'s
	 * get-then-set default, so that adapters with native atomic counters
	 * (Memcached) keep their atomicity when the primary is alive. Counter
	 * values may diverge between primary and fallback after a failover —
	 * that's an inherent multi-tier-cache trade-off.
	 */
	public function increment(string $key, int $howMuch = 1): int
	{
		return $this->execute(fn(AbstractCacheAdapter $a) => $a->increment($key, $howMuch));
	}

	public function decrement(string $key, int $howMuch = 1): int
	{
		return $this->execute(fn(AbstractCacheAdapter $a) => $a->decrement($key, $howMuch));
	}

}
