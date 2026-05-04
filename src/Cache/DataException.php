<?php declare(strict_types=1);

namespace Koldy\Cache;

/**
 * Thrown by cache adapters when the operation cannot complete because of how
 * the caller is using the cache — e.g. the key is too long, the value is too
 * big, the data was malformed/corrupted, or the arguments are invalid.
 *
 * These are application bugs, not infrastructure failures. The Cache failover
 * proxy does NOT catch this — it propagates so the caller can fix the bug
 * rather than silently falling back to a different adapter.
 *
 * Because it extends Koldy\Cache\Exception, callers that already catch the
 * base CacheException continue to work unchanged.
 *
 * @link https://koldy.net/framework/docs/2.0/cache.md
 */
class DataException extends Exception
{

}
