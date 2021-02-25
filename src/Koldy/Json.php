<?php declare(strict_types=1);

namespace Koldy;

use Koldy\Json\Exception;

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
	 * @return mixed in JSON format
	 * @throws Exception
	 * @link https://koldy.net/framework/docs/2.0/json.md
	 */
    public static function encode($data, int $flags = null, int $depth = null): string
    {
    	if ($flags === null) {
    		$flags = 0;
	    }

    	if ($depth === null) {
    		$depth = 512;
	    }

        $json = json_encode($data, $flags, $depth);

        if ($json === false) {
            $errNo = json_last_error();
            $msg = json_last_error_msg();
            throw new Exception("Unable to encode data to JSON, error ({$errNo}): {$msg}", $errNo);
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
    public static function decode(string $stringData, int $flags = null, int $depth = null): array
    {
	    if ($flags === null) {
		    $flags = 0;
	    }

	    if ($depth === null) {
		    $depth = 512;
	    }

        $decoded = json_decode($stringData, true, $depth, $flags);

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
	 * @return \stdClass
	 * @throws Exception
	 * @link https://koldy.net/framework/docs/2.0/json.md
	 */
    public static function decodeToObj(string $stringData, int $flags = null, int $depth = null): \stdClass
    {
	    if ($flags === null) {
		    $flags = 0;
	    }

	    if ($depth === null) {
		    $depth = 512;
	    }

        $decoded = json_decode($stringData, false, $depth, $flags);

        if ($decoded === null) {
            $errNo = json_last_error();
            $msg = json_last_error_msg();
            throw new Exception("Unable to decode JSON string, error ({$errNo}): {$msg}", $errNo);
        }

        return $decoded;
    }

}
