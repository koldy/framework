<?php declare(strict_types=1);

namespace Koldy\Convert;

use Koldy\Exception\ExtensionException;

/**
 * Someday, you'll encounter the situation when PHP can't handle really big numbers. If you search the internet, you'll
 * find out that you should be using PHP's BC Math lib that treats big numbers as string, which is fine. But, after a
 * while, you'll probably need to write those big numbers in shorter form. This class does exactly that. By default,
 * we're using all numbers, lower and uppercase ASCII letters for conversions which gives us numeric system per
 * base 62. If you want to use the number/letter combination you want, then please extend this class and override
 * getAvailableNumbers() method.
 *
 * This class requires BC Math which is available since PHP 4.0.4
 * @link https://www.php.net/manual/en/book.bc.php
 */
class NumericNotation
{

    /**
     * The "alphabet"
     */
    protected const NUMBERS = [
      0 => '0',
      1 => '1',
      2 => '2',
      3 => '3',
      4 => '4',
      5 => '5',
      6 => '6',
      7 => '7',
      8 => '8',
      9 => '9',
      10 => 'a',
      11 => 'b',
      12 => 'c',
      13 => 'd',
      14 => 'e',
      15 => 'f',
      16 => 'g',
      17 => 'h',
      18 => 'i',
      19 => 'j',
      20 => 'k',
      21 => 'l',
      22 => 'm',
      23 => 'n',
      24 => 'o',
      25 => 'p',
      26 => 'q',
      27 => 'r',
      28 => 's',
      29 => 't',
      30 => 'u',
      31 => 'v',
      32 => 'w',
      33 => 'x',
      34 => 'y',
      35 => 'z',
      36 => 'A',
      37 => 'B',
      38 => 'C',
      39 => 'D',
      40 => 'E',
      41 => 'F',
      42 => 'G',
      43 => 'H',
      44 => 'I',
      45 => 'J',
      46 => 'K',
      47 => 'L',
      48 => 'M',
      49 => 'N',
      50 => 'O',
      51 => 'P',
      52 => 'Q',
      53 => 'R',
      54 => 'S',
      55 => 'T',
      56 => 'U',
      57 => 'V',
      58 => 'W',
      59 => 'X',
      60 => 'Y',
      61 => 'Z'
    ];

	/**
	 * @throws ExtensionException
	 */
    private static function checkExtensionOrFail(): void
    {
    	if (!extension_loaded('bcmath')) {
    		throw new ExtensionException('BCMath extension is not loaded. Visit https://www.php.net/manual/en/bc.installation.php for more info');
	    }
    }

	/**
	 * Convert decimal number into your numeric system
	 *
	 * @param string $number
	 *
	 * @return string
	 * @throws Exception
	 * @throws ExtensionException
	 * @example 40487 is ax1
	 */
    public static function dec2big(string $number): string
    {
    	static::checkExtensionOrFail();

        $alphabet = static::NUMBERS;
        $number = trim((string)$number);

        if (strlen($number) == 0) {
            throw new Exception('Got empty number for dec2big, can not proceed');
        }

        $mod = (string)count($alphabet);
        $s = '';

        do {
            $x = bcdiv($number, $mod, 0);
            $left = bcmod($number, $mod);
            $char = $alphabet[$left];
            $s = $char . $s;

            $number = $x;
        } while ($x != '0');

        return $s;
    }

	/**
	 * The reverse procedure, convert number from your numeric system into decimal number
	 *
	 * @param string $alpha
	 *
	 * @return string because real number can reach the MAX_INT
	 * @throws Exception
	 * @throws ExtensionException
	 * @example ax1 is 40487
	 */
    public static function big2dec(string $alpha): string
    {
	    static::checkExtensionOrFail();

        if (strlen($alpha) <= 0) {
            throw new Exception('Got empty string in big2dec, can not proceed');
        }

        $alphabet = array_flip(static::NUMBERS);
        $mod = (string)count($alphabet);

        $x = '0';
        for ($i = 0, $j = strlen($alpha) - 1; $i < strlen($alpha); $i++, $j--) {
            $char = substr($alpha, $j, 1);

            if (!array_key_exists($char, $alphabet)) {
            	throw new Exception("Invalid numeric notation character on position {$i}: {$char}");
            }

            $val = $alphabet[$char];

            $x = bcadd($x, bcmul((string)$val, bcpow($mod, (string)$i)));
        }

        return $x;
    }

}
