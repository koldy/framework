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
     * @example this-is-good-formatted-123-slug
     * @example This-is-NOT-good-formatted-slug--contains-uppercase
     * @example slug-should never contain any-spaces
     * @example slug-should-never-contain-any-other-characters-like-šđčćž
     * @example this--is--bad--slug--because-it-has-double-dashes
     *
     * @param String $slug
     *
     * @return bool
     */
    public static function isSlug(string $slug): bool
    {
        return (bool)preg_match('/^[a-z0-9\-]+(-[a-z0-9]+)*$/', $slug);
    }

}
