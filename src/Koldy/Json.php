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
     *
     * @return mixed in JSON format
     * @throws Exception
     * @link http://koldy.net/docs/json#encode-decode
     */
    public static function encode($data): string
    {
        $json = json_encode($data);

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
     *
     * @return array
     * @throws Exception
     * @link http://koldy.net/docs/json#encode-decode
     */
    public static function decode(string $stringData): array
    {
        $decoded = json_decode($stringData, true);

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
     *
     * @return \stdClass
     * @throws Exception
     * @link http://koldy.net/docs/json#encode-decode
     */
    public static function decodeToObj(string $stringData): \stdClass
    {
        $decoded = json_decode($stringData, false);

        if ($decoded === null) {
            $errNo = json_last_error();
            $msg = json_last_error_msg();
            throw new Exception("Unable to decode JSON string, error ({$errNo}): {$msg}", $errNo);
        }

        return $decoded;
    }

}
