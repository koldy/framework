<?php declare(strict_types=1);

namespace Koldy\Adapter\Memcached;

use Memcached;
use Stringable;

class ResultCodeInfo implements Stringable
{

	public function __construct(private readonly int $resultCode)
	{

	}

	public function getResultCode(): int
	{
		return $this->resultCode;
	}

	/**
	 * Get the result code human-readable description. It is good fod logging and troubleshooting problems.
	 *
	 * @return string
	 */
	public function getDescription(): string
	{
		return match ($this->resultCode) {
			Memcached::RES_SUCCESS => 'The operation was successful.',
			Memcached::RES_FAILURE => 'The operation failed in some fashion.',
			Memcached::RES_HOST_LOOKUP_FAILURE => 'DNS lookup failed.',
			Memcached::RES_CONNECTION_FAILURE => 'A unknown error has occurred while trying to connect to a server.',
			Memcached::RES_WRITE_FAILURE => 'Failed to write network data.',
			Memcached::RES_READ_FAILURE => 'A read failure has occurred.',
			Memcached::RES_UNKNOWN_READ_FAILURE => 'Failed to read network data.',
			Memcached::RES_PROTOCOL_ERROR => 'Bad command in memcached protocol.',
			Memcached::RES_CLIENT_ERROR => 'Error on the client side.',
			Memcached::RES_SERVER_ERROR => 'Error on the server side.',
			Memcached::RES_DATA_EXISTS => 'Failed to do compare-and-swap: item you are trying to store has been modified since you last fetched it.',
			Memcached::RES_DATA_DOES_NOT_EXIST => 'The data requested with the key given was not found.',
			Memcached::RES_NOTSTORED => 'Item was not stored: but not because of an error. This normally means that either the condition for an "add" or a "replace" command wasn\'t met, or that the item is in a delete queue.',
			Memcached::RES_STORED => 'The requested object has been successfully stored on the server.',
			Memcached::RES_NOTFOUND => 'Item with this key was not found (with "get" operation or "cas" operations).',
			Memcached::RES_SERVER_MEMORY_ALLOCATION_FAILURE, Memcached::RES_MEMORY_ALLOCATION_FAILURE => 'An error has occurred while trying to allocate memory.',
			Memcached::RES_PARTIAL_READ => 'Partial network data read error.',
			Memcached::RES_SOME_ERRORS => 'Some errors occurred during multi-get.',
			Memcached::RES_NO_SERVERS => 'Server list is empty.',
			Memcached::RES_END => 'End of result set.',
			Memcached::RES_DELETED => 'The object requested by the key has been deleted.',
			Memcached::RES_VALUE => 'A value has been returned from the server (this is an internal condition only).',
			Memcached::RES_STAT => 'A "stat" command has been returned in the protocol.',
			Memcached::RES_ITEM => 'An item has been fetched (this is an internal error only).',
			Memcached::RES_ERRNO => 'System error.',
			Memcached::RES_FAIL_UNIX_SOCKET => 'A connection was not established with the server via a unix domain socket.',
			Memcached::RES_NOT_SUPPORTED => 'The given method is not supported in the server.',
			Memcached::RES_FETCH_NOTFINISHED => 'A request has been made, but the server has not finished the fetch of the last request.',
			Memcached::RES_TIMEOUT => 'The operation timed out.',
			Memcached::RES_BUFFERED => 'The operation was buffered.',
			Memcached::RES_BAD_KEY_PROVIDED => 'The key provided is not a valid key.',
			Memcached::RES_INVALID_HOST_PROTOCOL => 'The server you are connecting too has an invalid protocol. Most likely you are connecting to an older server that does not speak the binary protocol.',
			Memcached::RES_SERVER_MARKED_DEAD => 'The requested server has been marked dead.',
			Memcached::RES_UNKNOWN_STAT_KEY => 'The server you are communicating with has a stat key which has not be defined in the protocol.',
			Memcached::RES_E2BIG => 'Item is too large for the server to store.',
			Memcached::RES_INVALID_ARGUMENTS => 'The arguments supplied to the given function were not valid.',
			Memcached::RES_KEY_TOO_BIG => 'The key that has been provided is too large for the given server.',
			Memcached::RES_AUTH_PROBLEM => 'An unknown issue has occurred during authentication.',
			Memcached::RES_AUTH_FAILURE => 'The credentials provided are not valid for this server.',
			Memcached::RES_AUTH_CONTINUE => 'Authentication has been paused.',
			Memcached::RES_PARSE_ERROR => 'An error has occurred while trying to parse the configuration string. You should use memparse to determine what the error was.',
			Memcached::RES_PARSE_USER_ERROR => 'An error has occurred in parsing the configuration string.',
			Memcached::RES_DEPRECATED => 'The method that was requested has been deprecated.',
			Memcached::RES_IN_PROGRESS => 'Operation is still in progress.',
			Memcached::RES_SERVER_TEMPORARILY_DISABLED => 'Server is temporarily disabled.',
			Memcached::RES_MAXIMUM_RETURN => 'This in an internal only state.',
			Memcached::RES_CONNECTION_SOCKET_CREATE_FAILURE => 'Failed to create network socket.'
		};
	}

	/**
	 * Returns true for memcache success operations on read, write and delete. However, read the method's body to see
	 * exactly when it returns true.
	 *
	 * @return bool
	 */
	public function isSuccess(): bool
	{
		return in_array($this->resultCode, [
			Memcached::RES_SUCCESS,
			Memcached::RES_STORED,
			Memcached::RES_DELETED,
			Memcached::RES_END
		]);
	}

	/**
	 * This method exists because developers will usually want to check if operation is in error or not.
	 *
	 * @return bool
	 */
	public function isError(): bool
	{
		return !$this->isSuccess();
	}

	public function __toString(): string
	{
		return "Memcached result code #{$this->resultCode}: {$this->getDescription()}";
	}
}
