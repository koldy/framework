<?php declare(strict_types=1);

namespace Koldy\Validator;

/**
 * Class Validate - A helper class/shorthand for validating various data types
 * @package Koldy\Validator
 */
class Validate
{

	/**
	 * Is given string valid e-mail address?
	 *
	 * @param string $email
	 *
	 * @return boolean
	 */
	public static function isEmail(string $email): bool
	{
		return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
	}

	/**
	 * Is given string valid IP address?
	 *
	 * @param string $ip
	 *
	 * @return boolean
	 */
	public static function isIP(string $ip): bool
	{
		return filter_var($ip, FILTER_VALIDATE_IP) !== false;
	}

	/**
	 * Is given variable good formatted "slug".
	 * The "slug" is usually text used in URLs that uniquely defines some object.
	 *
	 * @param String $slug
	 *
	 * @return bool
	 * @example slug-should never contain any-spaces
	 * @example slug-should-never-contain-any-other-characters-like-šđčćž
	 * @example this--is--bad--slug--because-it-has-double-dashes
	 *
	 * @example this-is-good-formatted-123-slug
	 * @example This-is-NOT-good-formatted-slug--contains-uppercase
	 */
	public static function isSlug(string $slug): bool
	{
		return (bool)preg_match('/^[a-z0-9\-]+(-[a-z0-9]+)*$/', $slug);
	}

	/**
	 * Checks if value have the correct UUID format (the 8-4-4-4-12 hex strings)
	 *
	 * @param string $uuid
	 *
	 * @return bool
	 */
	public static function isUUID(string $uuid): bool
	{
		// not using regex validator because it's slower compared to this approach

		if (strlen($uuid) !== 36) {
			return false;
		}

		// validating 8-4-4-4-12 hex format
		$parts = explode('-', $uuid);

		if (count($parts) !== 5) {
			return false;
		}

		[$s1, $s2, $s3, $s4, $s5] = $parts;

		if (strlen($s1) !== 8 || strlen($s2) !== 4 || strlen($s3) !== 4 || strlen($s4) !== 4 || strlen($s5) !== 12) {
			return false;
		}

		foreach ($parts as $part) {
			if (!ctype_xdigit($part)) {
				return false;
			}
		}

		return true;
	}

}
