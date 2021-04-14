<?php declare(strict_types=1);

namespace Koldy;

use Koldy\Convert\Exception as ConvertException;

/**
 * This is utility class that has some common converting methods. Take a look at the methods and their PHP doc.
 *
 * @link https://koldy.net/framework/docs/2.0/converters.md
 */
class Convert
{

    /**
     * Measures for bytes
     *
     * @var array
     */
    private static $measure = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB', 'BB'];

    /**
     * Get file's measure
     *
     * @param float $size
     * @param int $count
     * @param int $round
     *
     * @return string
     */
    private static function getMeasure(float $size, int $count = 0, int $round = 0): string
    {
        if ($size >= 1024) {
	        return self::getMeasure(round($size / 1024, $round), ++$count, $round);
        } else {
            return round($size, $round) . ' ' . self::$measure[$count];
        }
    }

    /**
     * Get bytes size as string
     *
     * @param int $bytes
     * @param int $round round to how many decimals
     *
     * @return string
     * @example 2048 will return 2 KB
     *
     * @link https://koldy.net/framework/docs/2.0/converters.md#bytestostring
     */
    public static function bytesToString(int $bytes, int $round = 0): string
    {
        return self::getMeasure((float)$bytes, 0, $round);
    }

    /**
     * Get the number of bytes from string
     *
     * @param string $string
     *
     * @return int
     * @throws ConvertException
     * @example 1M will return 1048576
     *
     * @link https://koldy.net/framework/docs/2.0/converters.md#stringtobytes
     */
    public static function stringToBytes(string $string): int
    {
        $original = trim($string);
        $number = (int)$original;

        if ($number === $original || $number === 0) {
            return $number;
        } else {
            $char = strtoupper(substr($original, -1, 1));
            switch ($char) {
                case 'K': // KILO
                    return $number * 1024;
                    break;

                case 'M': // MEGA
                    return $number * pow(1024, 2);
                    break;

                case 'G': // GIGA
                    return $number * pow(1024, 3);
                    break;

                case 'T': // TERA
                    return $number * pow(1024, 4);
                    break;

                case 'P': // PETA
                    return $number * pow(1024, 5);
                    break;

                case 'E': // EXA
                    return $number * pow(1024, 6);
                    break;

                default:
                    throw new ConvertException('Not implemented sizes greater than exabytes');
                    break;
            }
        }
    }

    /**
     * Convert given string into proper UTF-8 string
     *
     * @param string $string
     *
     * @return string
     * @throws ConvertException
     * @author Simon Brüchner (@powtac)
     *
     * @link https://koldy.net/framework/docs/2.0/converters.md#stringtoutf8
     * @link http://php.net/manual/en/function.utf8-encode.php#102382
     */
    public static function stringToUtf8(string $string): string
    {
        if (!mb_check_encoding($string, 'UTF-8') || !($string === mb_convert_encoding(mb_convert_encoding($string, 'UTF-32', 'UTF-8'), 'UTF-8', 'UTF-32'))) {

            $string = mb_convert_encoding($string, 'UTF-8');

            if (!mb_check_encoding($string, 'UTF-8')) {
                throw new ConvertException('Can not convert given string to UTF-8');
            }
        }

        return $string;
    }

}
