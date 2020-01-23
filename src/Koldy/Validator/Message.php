<?php declare(strict_types=1);

namespace Koldy\Validator;

use Koldy\Validator\ConfigException as ValidatorException;

/**
 * Class ErrorMessage
 * @package Koldy\Validator\Message
 *
 * Class that handles Validator error messages
 */
class Message
{

    public const PRESENT = 0;
    public const REQUIRED = 1;
    public const MIN_VALUE = 2;
    public const MAX_VALUE = 3;
    public const MIN_LENGTH = 4;
    public const MAX_LENGTH = 5;
    public const NUMERIC = 6;
    public const PRIMITIVE = 7;
    public const LENGTH = 8;
    public const INTEGER = 9;
    public const ALPHA_NUM = 10;
    public const ALPHA = 11;
    public const EMAIL = 12;
    public const SLUG = 13;
    public const IS = 14;
    public const DECIMAL = 15;
    public const HEX = 16;
    public const SAME = 17;
    public const DIFFERENT = 18;
    public const DATE = 19;
    public const IS_NOT_ARRAY = 20;
    public const ARRAY_WRONG_COUNT = 21;
    public const ANY_OF = 22;
    public const NOT_UNIQUE = 23;
    public const NO_RECORD = 24;
    public const CSRF_FAILED = 25;
    public const BOOL = 26;
    public const STARTS_WITH = 27;
    public const ENDS_WITH = 28;

    /**
     * Flag if this class has been initialized or not
     *
     * @var bool
     */
    protected static $initialized = false;

    /**
     * Array of validator error messages
     *
     * @var array
     */
    protected static $messages = [
      self::PRESENT => 'Parameter {param} is not present', // {param}, {value}
      self::REQUIRED => 'This field is required', // {param}, {value}
      self::MIN_VALUE => 'Has to be at least {min}', // {param}, {value}, {min}
      self::MAX_VALUE => 'Has to be less than {max}', // {param}, {value}, {max}
      self::MIN_LENGTH => 'Has to be at least {min} characters long', // {param}, {value}, {min}
      self::MAX_LENGTH => 'Has to be less than {max} characters long', // {param}, {value}, {max}
      self::NUMERIC => 'Has to be numeric', // {param}, {value}
      self::PRIMITIVE => '{param} has to be primitive value, not array nor object', // {param}
      self::LENGTH => 'This field has to be exactly {length} characters long', // {param}, {value}
      self::INTEGER => 'This field has to be integer', // {param}, {value}
      self::ALPHA_NUM => 'This field is not alpha numeric', // {param}, {value}
      self::ALPHA => 'This field does not contain alpha characters only', // {param}, {value}
      self::EMAIL => 'This is not valid e-mail address', // {param}, {value}
      self::SLUG => 'This is not valid URL slug', // {param}, {value}
      self::IS => 'This field doesn\'t have required value', // {param}, {value}
      self::DECIMAL => 'This value can\'t have more than {decimals} decimals', // {param}, {value}, {decimals}
      self::HEX => 'This value should be hexadecimal number', // {param}, {value}
      self::SAME => 'This has to be the same as {otherField}', // {param}, {value}, {otherField}, {otherValue}
      self::DIFFERENT => 'This value has to be different than {otherField}', // {param}, {value}, {otherField}, {otherValue}
      self::DATE => 'This value is not valid date', // {param}, {value}
      self::IS_NOT_ARRAY => '{param} is not array', // {param}
      self::ARRAY_WRONG_COUNT => '{param} should have {requiredCount} elements, not {currentCount}', // {param}, {requiredCount}, {currentCount}
      self::ANY_OF => 'This field doesn\'t have any allowed value', // {param}, {value}, {allowedValues}
      self::NOT_UNIQUE => 'We already have this', // {param}, {value}, {exceptionValue}, {exceptionField}
      self::NO_RECORD => 'This value does not exists in database', // {param}, {value}, {field}
      self::CSRF_FAILED => 'CSRF check has failed', // {param}
      self::BOOL => 'Parameter is not boolean', // {param}
      self::STARTS_WITH => 'Value should start with {startsWith}', // {param}, {value}, {startsWith}
      self::ENDS_WITH => 'Value should end with {endsWith}' // {param}, {value}, {endsWith}
    ];

    /**
     * Initialize this class. Override if needed, e.g. in case if you want to pull translations from some other source
     */
    public static function init()
    {
        static::$initialized = true;
    }

    /**
     * Was this class initialized or not?
     *
     * @return bool
     */
    public static function isInitialized(): bool
    {
        return static::$initialized;
    }

	/**
	 * @param int $constant
	 * @param array|null $data
	 *
	 * @return string
	 * @throws ConfigException
	 * @throws Exception
	 */
    public static function getMessage(int $constant, array $data = null): string
    {
        if (!static::isInitialized()) {
            static::init();
        }

        if (!isset(static::$messages[$constant])) {
            throw new ValidatorException("Can't get Validator message, undefined Validator constant={$constant}");
        }

        $msg = static::$messages[$constant];
        $return = null;

        if ($msg instanceof \Closure) {
	        try {
		        $return = call_user_func($msg, $data);
	        } catch (\Exception | \Throwable $e) {
		        throw new Exception("Failed to execute custom validator function: {$e->getMessage()}", $e->getCode(), $e);
	        }

            if (!is_string($return)) {
                throw new ValidatorException("Returned type after calling user function on validator message constant={$constant} is not string");
            }
        } else if (is_string($msg)) {
            $return = $msg;

            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    if ($value === null || is_scalar($value)) {
                        if ($value !== null) {
                            $return = str_replace("{{$key}}", $value, $return);
                        }
                    } else {
                        throw new ValidatorException("Parameters for validator error message contains non-primitive value in key={$key}");
                    }
                }
            }
        } else {
            $type = gettype($msg);
            throw new ValidatorException("Unhandled validator type, expected Closure or string, got={$type}");
        }

        return $return;
    }

    /**
     * Set custom validation message
     *
     * @param int $constant
     * @param string $message
     */
    public static function setMessageString(int $constant, string $message)
    {
        static::$messages[$constant] = $message;
    }

    /**
     * Set custom validation message by passing function that will be executed when message occurs
     *
     * @param int $constant
     * @param \Closure $userFunction The user function must return string, otherwise \Koldy\Validator\Exception will be thrown
     */
    public static function setMessageFunction(int $constant, \Closure $userFunction)
    {
        static::$messages[$constant] = $userFunction;
    }

}
