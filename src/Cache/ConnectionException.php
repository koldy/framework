<?php declare(strict_types=1);

namespace Koldy\Cache;

/**
 * Thrown by cache adapters when an underlying infrastructure failure prevents
 * the operation from completing — e.g. a TCP connection timed out, the host
 * could not be resolved, the server was marked dead, or a database connection
 * was refused.
 *
 * The Cache failover proxy catches this exception specifically (and only this
 * exception) to decide whether to fall over to the next adapter in the chain.
 *
 * Because it extends Koldy\Cache\Exception, callers that already catch the
 * base CacheException continue to work unchanged.
 *
 * @link https://koldy.net/framework/docs/2.0/cache.md
 */
class ConnectionException extends Exception
{

}
