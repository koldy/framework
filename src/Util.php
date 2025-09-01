<?php declare(strict_types=1);

namespace Koldy;

use DateTime;
use DateTimeZone;
use Exception;

/**
 * This class contains some common functions that are helpful in many projects.
 */
class Util
{

    /**
     * Generate random string using openssl_random_pseudo_bytes(). It'll return all possible English lower
     * and uppercase letters with numbers, not just hexadecimal string
     *
     * @param int $length
     *
     * @return string
     * @throws Exception
     */
	public static function randomString(int $length): string
    {
        $random = '';
        $passes = 0;

        do {
            foreach (str_split(openssl_random_pseudo_bytes($length * 3)) as $c) {
                $ascii = ord($c);

                if ($ascii >= 48 && $ascii <= 57 || $ascii >= 65 && $ascii <= 90 || $ascii >= 97 && $ascii <= 122) {
                    $random .= chr($ascii);
                }
            }
        } while (strlen($random) < $length && $passes++ < 50);

        if ($passes == 50) {
            throw new Exception('Unable to generate random string even after a lot passes... something is really wrong!');
        }

        return substr($random, 0, $length);
    }

    /**
     * Get the hex representation of string
     *
     * @param string $x
     *
     * @return string
     */
	public static function str2hex(string $x): string
    {
        $string = '';

        foreach (str_split($x) as $char) {
            $string .= sprintf('%02X', ord($char));
        }

        return $string;
    }

    /**
     * Clean given string from tabs, new lines and double spaces
     *
     * @param string $string
     * @return string
     */
	public static function cleanString(string $string): string
    {
        if ($string === '') {
            return '';
        }

        return trim(preg_replace(['/\s{2,}/', '/[\t\n]/'], ' ', $string));
    }

    /**
     * Convert (') into (& apos ;)
     *
     * @param string $string
     *
     * @return string
     */
	public static function apos(string $string): string
    {
        return str_replace("'", '&apos;', $string);
    }

    /**
     * Parse quotes and return it with html entities
     *
     * @param string $string
     *
     * @return string
     * @example " -> & quot ;
     */
	public static function quotes(string $string): string
    {
        return str_replace('"', '&quot;', $string);
    }

    /**
     * Parse "<" and ">" and return it with html entities
     *
     * @param string $string
     *
     * @return string
     * @example "<" and ">" -> "&lt;" and "&gt;"
     */
	public static function tags(string $string): string
    {
        $string = str_replace('<', '&lt;', $string);
        return str_replace('>', '&gt;', $string);
    }

    /**
     * Truncate the long string properly
     *
     * @param string $string
     * @param int $length default 80 [optional]
     * @param string $etc suffix string [optional] default '...'
     * @param bool $breakWords [optional] default false, true to cut the words in text
     * @param bool $middle [optional] default false
     *
     * @return string
     * @throws Exception
     */
	public static function truncate(string $string, int $length = 80, string $etc = '...', bool $breakWords = false, bool $middle = false): string
    {
        if ($length == 0) {
            return '';
        }

        $encoding = Application::getEncoding();

        if (mb_strlen($string, $encoding) > $length) {
            $length -= min($length, mb_strlen($etc, $encoding));

            if (!$breakWords && !$middle) {
                $string = preg_replace('/\s+?(\S+)?$/', '', mb_substr($string, 0, $length + 1, $encoding));
            }

            if (!$middle) {
                return mb_substr($string, 0, $length, $encoding) . $etc;
            } else {
                return mb_substr($string, 0, (int)round($length / 2), $encoding) . $etc . mb_substr($string, (int)round(-$length / 2), null, $encoding);
            }
        } else {
            return $string;
        }
    }

    /**
     * When having plain text with paragraphs and rows delimited only with new
     * line and you need to make HTML paragraphs from that omitted with <p>
     * tag, then use this method.
     *
     * @param string $string text
     *
     * @return string
     * @example text "Lorem ipsum\n\ndolor sit amet\nperiod." will become "<p>Lorem ipsum</p><p>dolor sit amet<br/>period.</p>"
     */
	public static function p(string $string): string
    {
        $string = str_replace("\n\n", '</p><p>', $string);
        $string = str_replace("\n", '<br/>', $string);
        return "<p>{$string}</p>";
    }

	/**
	 * Detect URLs in text and replace them with HTML A tag
	 *
	 * @param string $text
	 * @param string|null $target optional, default _blank
	 *
	 * @return string
	 */
	public static function a(string $text, string|null $target = null): string
    {
        return preg_replace('@((https?://)?([-\w]+\.[-\w\.]+)+\w(:\d+)?(/([-\w/_\.]*(\?\S+)?)?)*)@',
          "<a href=\"\$1\"" . ($target != null ? " target=\"{$target}\"" : '') . ">$1</a>", $text);
    }

    /**
     * Parse text so you can place it inside of HTML attribute
     *
     * @param string $text
     *
     * @return string
     */
	public static function attributeValue(string $text): string
    {
        return static::quotes(static::apos(static::tags($text)));
    }

    /**
     * Check if string starts with given string - it supports UTF-8, but it's case sensitive
     *
     * @param string $yourString
     * @param string $startsWith
     * @param string|null $encoding - by default, using application config (encoding) or uses UTF-8 by default
     *
     * @return bool
     * @throws Exception
     */
	public static function startsWith(string $yourString, string $startsWith, string|null $encoding = null): bool
    {
        return mb_substr($yourString, 0, mb_strlen($startsWith, $encoding ?? Application::getEncoding()), $encoding ?? Application::getEncoding()) === $startsWith;
    }

    /**
     * Check if string ends with given string - it supports UTF-8, but it's case sensitive
     *
     * @param string $yourString
     * @param string $endsWith
     * @param string|null $encoding - by default, using application config (encoding) or uses UTF-8 by default
     *
     * @return bool
     * @throws Exception
     */
	public static function endsWith(string $yourString, string $endsWith, string|null $encoding = null): bool
    {
        return mb_substr($yourString, 0 - mb_strlen($endsWith, $encoding ?? Application::getEncoding()), null, $encoding ?? Application::getEncoding()) === $endsWith;
    }

    /**
     * This method returns string prepared to be used in URLs as slugs
     *
     * @param string $string
     *
     * @return string
     * @example "Your new - title" will become "your-new-title"
     * @example "Vozač napravio 1500€ štete" will become "vozac-napravio-1500eur-stete"
     */
	public static function slug(string $string): string
    {
        if ($string == '') {
            return '';
        }

        $s = strip_tags(trim($string));

        $table = [
          'Š' => 'S',
          'š' => 's',
          'Đ' => 'Dj',
          'đ' => 'dj',
          'Ž' => 'Z',
          'ž' => 'z',
          'Č' => 'C',
          'č' => 'c',
          'Ć' => 'C',
          'ć' => 'c',
          'À' => 'A',
          'Á' => 'A',
          'Â' => 'A',
          'Ã' => 'A',
          'Ä' => 'A',
          'Å' => 'A',
          'Æ' => 'A',
          'Ç' => 'C',
          'È' => 'E',
          'É' => 'E',
          'Ê' => 'E',
          'Ë' => 'E',
          'Ì' => 'I',
          'Í' => 'I',
          'Î' => 'I',
          'Ï' => 'I',
          'Ñ' => 'N',
          'Ò' => 'O',
          'Ó' => 'O',
          'Ô' => 'O',
          'Õ' => 'O',
          'Ö' => 'O',
          'Ø' => 'O',
          'Ù' => 'U',
          'Ú' => 'U',
          'Û' => 'U',
          'Ü' => 'U',
          'Ý' => 'Y',
          'Þ' => 'B',
          'ß' => 'Ss',
          'à' => 'a',
          'á' => 'a',
          'â' => 'a',
          'ã' => 'a',
          'ä' => 'a',
          'å' => 'a',
          'æ' => 'a',
          'ç' => 'c',
          'è' => 'e',
          'é' => 'e',
          'ê' => 'e',
          'ë' => 'e',
          'ì' => 'i',
          'í' => 'i',
          'î' => 'i',
          'ï' => 'i',
          'ð' => 'o',
          'ñ' => 'n',
          'ò' => 'o',
          'ó' => 'o',
          'ô' => 'o',
          'õ' => 'o',
          'ö' => 'o',
          'ø' => 'o',
          'ù' => 'u',
          'ú' => 'u',
          'û' => 'u',
          'ý' => 'y',
          'þ' => 'b',
          'ÿ' => 'y',
          'Ŕ' => 'R',
          'ŕ' => 'r',
        ];

        $s = strtr($s, $table);

        $rpl = [
          '/(,|;|\!|\?|:|&|\+|\=|-|\'|\/|\*|\t|\n|\%|#|\^|\(|\)|\[|\]|\{|\}|\.)/' => '-',

          '/≈°/' => 's',
          '/ƒë/' => 'd',
          '/ƒç/' => 'c',
          '/ƒá/' => 'c',
          '/≈æ/' => 'z',
          '/≈†/' => 's',
          '/ƒê/' => 'd',
          '/ƒå/' => 'c',
          '/ƒÜ/' => 'c',
          '/≈Ω/' => 'z',

          '/&353;/' => 's',
          '/&273;/' => 'd',
          '/&269;/' => 'c',
          '/&263;/' => 'c',
          '/&382;/' => 'z',
          '/&351;/' => 'S',
          '/&272;/' => 'D',
          '/&268;/' => 'C',
          '/&262;/' => 'C',
          '/&381;/' => 'Z'
        ];

        $s = preg_replace(array_keys($rpl), array_values($rpl), $s);

        $s = str_replace('\\', '', $s);
        $s = str_replace('¬Æ', '-', $s);
        $s = str_replace('‚Äì', '-', $s);
        $s = str_replace('¬©', '-', $s);
        $s = str_replace('√ü', '', $s);
        $s = str_replace('’', '', $s);

        $s = str_replace('€', 'eur', $s);
        $s = str_replace('$', 'usd', $s);
        $s = str_replace('£', 'pound', $s);
        $s = str_replace('¥', 'yen', $s);

        $s = str_replace(' ', '-', $s);

        while (str_contains($s, '--')) {
            $s = str_replace('--', '-', $s);
        }

        $s = preg_replace('~[^\\pL\d]+~u', '-', $s);
        $s = trim($s, '-');
        $s = iconv('utf-8', 'us-ascii//IGNORE//TRANSLIT', $s);
        $s = strtolower($s);
        $s = preg_replace('~[^-\w]+~', '', $s);

        return $s;
    }

    /**
     * Make a camel case string out of given string
     *
     * @param string $string
     * @param array|null $noStrip
     * @param bool|null $lowerCaseFirstLetter
     *
     * @return string
     */
    public static function camelCase(string $string, array|null $noStrip = null, bool|null $lowerCaseFirstLetter = null): string
    {
        // non-alpha and non-numeric characters become spaces
        $string = preg_replace('/[^a-z0-9' . implode('', $noStrip ?? []) . ']+/i', ' ', $string);
        $string = trim($string);
        $string = ucwords($string);
        $string = str_replace(' ', '', $string);

        if ($lowerCaseFirstLetter === null || $lowerCaseFirstLetter === true) {
            $string = lcfirst($string);
        }

        return $string;
    }

    /**
     * Checks if array is associative or not.
     *
     * @param array $array
     *
     * @return bool
     */
	public static function isAssociativeArray(array $array): bool
    {
        return array_values($array) !== $array;
    }

    /**
     * True if given parameter is binary string
     *
     * @param $str
     *
     * @return bool
     */
	public static function isBinary($str): bool
    {
        return is_string($str) && preg_match('~[^\x20-\x7E\t\r\n]~', $str) > 0;
    }

    /**
     * Gets relative path from one absolute path to another
     *
     * @param string $from
     * @param string $to
     * @return string
     *
     * @link https://stackoverflow.com/questions/2637945/getting-relative-path-from-absolute-path-in-php
     */
	public static function getRelativePath(string $from, string $to): string
    {
        // some compatibility fixes for Windows paths
        $from = is_dir($from) ? rtrim($from, '\/') . '/' : $from;
        $to = is_dir($to) ? rtrim($to, '\/') . '/' : $to;
        $from = str_replace('\\', '/', $from);
        $to = str_replace('\\', '/', $to);

        $from = explode('/', $from);
        $to = explode('/', $to);
        $relPath = $to;

        foreach ($from as $depth => $dir) {
            // find first non-matching dir
            if ($dir === $to[$depth]) {
                // ignore this directory
                array_shift($relPath);
            } else {
                // get number of remaining dirs to $from
                $remaining = count($from) - $depth;
                if ($remaining > 1) {
                    // add traversals up to first matching dir
                    $padLength = (count($relPath) + $remaining - 1) * -1;
                    $relPath = array_pad($relPath, $padLength, '..');
                    break;
                } else {
                    $relPath[0] = './' . $relPath[0];
                }
            }
        }
        return implode('/', $relPath);
    }

	/**
	 * This method exists because gmdate('Y-m-d H:i:s.u') doesn't return milliseconds. This method creates a DateTime
	 * with milliseconds, so you can format your current time with "Y-m-d H:i:s.u"
	 *
	 * @param string|null $format  default is "Y-m-d H:i:s.u"
	 * @param string|null $timezone  default is "UTC"
	 *
	 * @return string
	 * @throws Exception
	 */
	public static function now(string|null $format = null, string|null $timezone = null): string
	{
		return (new DateTime('now', new DateTimeZone($timezone ?? 'UTC')))->format($format ?? 'Y-m-d H:i:s.u');
	}

	/**
	 * Parses given multipart content into array of params
	 *
	 * @param string $input the whole multipart content with boundary marks, that one that can be got with file_get_contents('php://input')
	 * @param string $contentType pass content type header, such as information from $_SERVER['CONTENT_TYPE']
	 *
	 * @return array
	 * @link https://stackoverflow.com/questions/5483851/manually-parse-raw-multipart-form-data-data-with-php/5488449#5488449
	 */
	public static function parseMultipartContent(string $input, string $contentType): array
	{
		if ($input === '') {
			return [];
		}

		$data = [];

		// grab multipart boundary from content type header
		preg_match('/boundary=(.*)$/', $contentType, $matches);
		$boundary = $matches[1];

		// split content by boundary and get rid of last -- element
		$a_blocks = preg_split("/-+$boundary/", $input);
		array_pop($a_blocks);

		// loop data blocks
		foreach ($a_blocks as $id => $block) {
			if (!empty($block)) {
				// you'll have to var_dump $block to understand this and maybe replace \n or \r with a visibile char

				// parse uploaded files
				if (str_contains($block, 'application/octet-stream')) {
					// match "name", then everything after "stream" (optional) except for prepending newlines
					preg_match('/name=\"([^\"]*)\".*stream[\n|\r]+([^\n\r].*)?$/s', $block, $matches);
				} // parse all other fields
				else {
					// match "name" and optional value in between newline sequences
					preg_match('/name=\"([^\"]*)\"[\n|\r]+([^\n\r].*)?\r$/s', $block, $matches);
				}

				$data[$matches[1]] = $matches[2] ?? '';
			}
		}

		return $data;
	}

	/**
	 * Generate random UUID v4 string compliant with RFC 4211. Code is taken from php.net, wrote by Andrew Moore
	 *
	 * @return string
	 * @link https://www.php.net/manual/en/function.uniqid.php#94959
	 */
	public static function randomUUIDv4(): string {
		return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',

			// 32 bits for "time_low"
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),

			// 16 bits for "time_mid"
			mt_rand(0, 0xffff),

			// 16 bits for "time_hi_and_version",
			// four most significant bits holds version number 4
			mt_rand(0, 0x0fff) | 0x4000,

			// 16 bits, 8 bits for "clk_seq_hi_res",
			// 8 bits for "clk_seq_low",
			// two most significant bits holds zero and one for variant DCE1.1
			mt_rand(0, 0x3fff) | 0x8000,

			// 48 bits for "node"
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);
	}

	/**
	 * Pick the value from array by key. If key doesn't exist, return default value. If key exists, but value is null, return default value.
	 *
	 * @param string $key name of the key to pick from the input array
	 * @param array $input the input array to go through
	 * @param string|int|float|bool|null $default default value to return if key doesn't exist or value is null; default is null
	 * @param array|null $allowedValues if set, then only values from this array are allowed; if value is not in this array, then default value is returned; values will be strictly compared
	 *
	 * @return mixed
	 */
	public static function pick(string $key, array $input, string|int|float|bool|null $default = null, array|null $allowedValues = null): mixed
	{
		if (!array_key_exists($key, $input)) {
			// there's no key, return default
			return $default;
		}

		// otherwise, key is found
		$value = $input[$key]; // this could still be null

		if ($value === null) {
			// treat as not found and return default
			return $default;
		}

		// now, the $value is not null

		if ($allowedValues !== null) {
			if (in_array($value, $allowedValues, true)) {
				return $value;
			}

			return $default;
		}

		return $value;
	}
}
