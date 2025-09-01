<?php declare(strict_types=1);

namespace Koldy;

use Koldy\Json\Exception;
use stdClass;

/**
 * Class Json - A helper class for working with JSON notation
 */
class Json
{

	/**
	 * JSON helper to quickly encode some data
	 *
	 * @param mixed $data
	 * @param int|null $flags
	 * @param int|null $depth
	 *
	 * @return string in JSON format
	 * @throws Exception
	 * @link https://koldy.net/framework/docs/2.0/json.md
	 */
	public static function encode(mixed $data, int|null $flags = null, int|null $depth = null): string
	{
		$json = json_encode($data, $flags ?? 0, $depth ?? 512);

		if ($json === false) {
			$errNo = json_last_error();
			$msg = json_last_error_msg();
			throw new Exception("Unable to encode data to JSON, error ({$errNo}): {$msg}", $errNo);
		}

		// Check if result is a primitive JSON value (not an object or array)
		if ($json[0] !== '{' && $json[0] !== '[') {
			throw new Exception('JSON encoding resulted in primitive value, structured JSON required');
		}

		return $json;
	}

	/**
	 * JSON helper to quickly decode JSON string into array or stdClass. Returns array by default. Pass TRUE to
	 * $returnObject to get the stdClass.
	 *
	 * @param string $stringData
	 * @param int|null $flags
	 * @param int|null $depth
	 *
	 * @return array
	 * @throws Exception
	 * @link https://koldy.net/framework/docs/2.0/json.md
	 */
	public static function decode(string $stringData, int|null $flags = null, int|null $depth = null): array
	{
		$decoded = json_decode($stringData, true, $depth ?? 512, $flags ?? 0);

		if ($decoded === null) {
			$errNo = json_last_error();
			$msg = json_last_error_msg();
			throw new Exception("Unable to decode JSON string, error ({$errNo}): {$msg}", $errNo);
		}

		return $decoded;
	}

	/**
	 * JSON helper to quickly decode JSON string into array or stdClass. Returns array by default. Pass TRUE to
	 * $returnObject to get the stdClass.
	 *
	 * @param string $stringData
	 * @param int|null $flags
	 * @param int|null $depth
	 *
	 * @return stdClass
	 * @throws Exception
	 * @link https://koldy.net/framework/docs/2.0/json.md
	 */
	public static function decodeToObj(string $stringData, int|null $flags = null, int|null $depth = null): stdClass
	{
		$decoded = json_decode($stringData, false, $depth ?? 512, $flags ?? 0);

		if ($decoded === null) {
			$errNo = json_last_error();
			$msg = json_last_error_msg();
			throw new Exception("Unable to decode JSON string, error ({$errNo}): {$msg}", $errNo);
		}

		return $decoded;
	}

}
