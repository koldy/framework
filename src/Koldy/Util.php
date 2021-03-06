<?php declare(strict_types=1);

namespace Koldy;

/**
 * Class that handles some common stuff.
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
        $string = str_replace('>', '&gt;', $string);
        return $string;
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
     * @param string $target optional, default _blank
     *
     * @return string
     */
    public static function a(string $text, string $target = null): string
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
    public static function startsWith(string $yourString, string $startsWith, string $encoding = null): bool
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
    public static function endsWith(string $yourString, string $endsWith, string $encoding = null): bool
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

        while (strpos($s, '--') !== false) {
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
     * @return mixed|string
     */
    public static function camelCase(string $string, array $noStrip = null, bool $lowerCaseFirstLetter = null)
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

}
