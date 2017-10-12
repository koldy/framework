<?php declare(strict_types=1);

namespace Koldy;

use Koldy\Convert\Exception as ConvertException;

/**
 * This is utility class that has some common converting methods. Take a look at the methods and their PHP doc.
 */
class Convert
{

    /**
     * Measures for bytes
     *
     * @var array
     */
    private static $measure = ['B', 'KB', 'MB', 'GB', 'TB', 'PT'];

    /**
     * Get file's measure
     *
     * @param int $size
     * @param int $count
     * @param int $round
     *
     * @return string
     */
    private static function getMeasure(int $size, int $count = 0, int $round = 0): string
    {
        if ($size >= 1024) {
            return self::getMeasure((int)round($size / 1024), ++$count, $round);
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
     */
    public static function bytesToString(int $bytes, int $round = 0): string
    {
        return self::getMeasure($bytes, 0, $round);
    }

    /**
     * Get the number of bytes from string
     *
     * @param string $string
     *
     * @return int
     * @throws ConvertException
     * @example 1M will return 1048576
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
     * Convert kilogram (kg) to pounds (lb)
     *
     * @param float $kilograms
     *
     * @return float
     *
     * @deprecated
     */
    public static function kilogramToPounds(float $kilograms): float
    {
        return $kilograms * 2.20462262;
    }

    /**
     * Convert pounds (lb) to kilograms (kg)
     *
     * @param float $pounds
     *
     * @return float
     *
     * @deprecated
     */
    public static function poundToKilograms(float $pounds): float
    {
        return $pounds / 2.20462262;
    }

    /**
     * Convert meter (m) to foot (ft)
     *
     * @param float $meters
     *
     * @return float
     *
     * @deprecated
     */
    public static function meterToFoot(float $meters): float
    {
        return $meters * 3.2808399;
    }

    /**
     * Convert foot (ft) to meters (m)
     *
     * @param float $feet
     *
     * @return float
     *
     * @deprecated
     */
    public static function footToMeters(float $feet): float
    {
        return $feet / 3.2808399;
    }

    /**
     * Convert centimeters (cm) to inches (in)
     *
     * @param float $centimeters
     *
     * @return float
     *
     * @deprecated
     */
    public static function centimeterToInches(float $centimeters): float
    {
        return $centimeters * 0.393700787;
    }

    /**
     * Convert inches (in) to centimeters (cm)
     *
     * @param float $inches
     *
     * @return float
     *
     * @deprecated
     */
    public static function inchToCentimeters(float $inches): float
    {
        return $inches / 0.393700787;
    }

    /**
     * Convert given string into proper UTF-8 string
     *
     * @param string $string
     *
     * @return string
     * @throws ConvertException
     * @author Simon Brüchner (@powtac)
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
