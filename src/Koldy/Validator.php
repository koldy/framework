<?php declare(strict_types=1);

namespace Koldy;

use Koldy\Db\Model;
use Koldy\Security\Csrf;
use Koldy\Security\Csrf\Exception as CsrfException;
use Koldy\Validator\{
    ConfigException, Exception as InvalidDataException, ConfigException as ValidatorConfigException, Message, Validate
};

class Validator
{

	protected const DATA_SOURCE_REQUEST = 'request';
	protected const DATA_SOURCE_PARAMETER = 'parameter';

    /**
     * Validation rules
     *
     * @var array
     */
    protected $rules;

    /**
     * Data to be validated
     *
     * @var array
     */
    protected $data;

	/**
	 * What is the data source that is being validated? Request or manual?
	 *
	 * @var string
	 */
    protected $dataSource = null;

    /**
     * @var array
     */
    protected $invalid = [];

    /**
     * @var array
     */
    protected $valid = [];

    /**
     * Make validation more strict by setting this to true - if true, we'll expect parameters only from rules, nothing less, nothing more
     *
     * @var bool
     */
    protected $only = false;

    /**
     * Enable automatic CSRF check
     *
     * @var bool
     */
    protected $csrfEnabled = false;

    /**
     * @var bool
     */
    private $validated = null;

    /**
     * Validator constructor.
     *
     * @param array $rules
     * @param array|null $data
     * @throws Exception
     */
    public function __construct(array $rules, array $data = null)
    {
        if ($data === null) {
            $this->data = Request::getAllParameters();
            $this->dataSource = static::DATA_SOURCE_REQUEST;
            $this->csrfEnabled = Csrf::isEnabled();

            $csrfParameter = Csrf::getParameterName();
            if ($csrfParameter !== null && !isset($rules[$csrfParameter])) {
                $rules[$csrfParameter] = 'required|csrf';
            }
        } else {
	        $this->dataSource = static::DATA_SOURCE_PARAMETER;
            $this->data = $data;
        }

        $this->rules = $rules;
    }

    /**
     * @param array $rules
     * @param array|null $data
     * @param bool $validateAndThrowException
     *
     * @return Validator
     * @throws Exception
     * @throws InvalidDataException
     */
    public static function create(array $rules, array $data = null, bool $validateAndThrowException = true): Validator
    {
        $validator = new static($rules, $data);

        if ($validateAndThrowException) {
            if (!$validator->isAllValid()) {
                $exception = new InvalidDataException('Validator exception');
                $exception->setValidator($validator);
                throw $exception;
            }
        }

        return $validator;
    }

    /**
     * Create validator, but allow only given data to be validated, making it even more strict
     *
     * @param array $rules
     * @param array|null $data
     * @param bool $validateAndThrowException
     *
     * @return Validator
     * @throws Exception
     * @throws InvalidDataException
     */
    public static function only(array $rules, array $data = null, bool $validateAndThrowException = true): Validator
    {
        $validator = new static($rules, $data);
        $validator->limitDataToRules(true);

        if ($validateAndThrowException) {
            if (!$validator->isAllValid()) {
                $exception = new InvalidDataException('Validator exception');
                $exception->setValidator($validator);
                throw $exception;
            }
        }

        return $validator;
    }

    /**
     * Make validation more strict
     *
     * @param bool $only
     *
     * @return Validator
     */
    public function limitDataToRules(bool $only = true): Validator
    {
        $this->only = $only;
        return $this;
    }

    /**
     * Is automatic CSRF check enabled or not
     *
     * @return bool
     * @deprecated
     */
    public function isCsrfEnabled(): bool
    {
        return $this->csrfEnabled;
    }

    /**
     * Manually enable CSRF check. This has to be enabled manually if you're passing
     * custom data to validator and still want to do CSRF check.
     * @deprecated
     */
    public function enableCsrfCheck(): void
    {
        $this->csrfEnabled = true;
    }

    /**
     * You might want to disable automatic CSRF check in some cases.
     * @deprecated
     */
    public function disableCsrfCheck(): void
    {
        $this->csrfEnabled = false;
    }

    /**
     * @return array
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Get the data we're going to validate
     *
     * @param bool $trimStrings
     *
     * @return array
     * @throws Config\Exception
     * @throws Exception
     */
    public function getData(bool $trimStrings = null): array
    {
        $trimStrings = ($trimStrings === null) ? true : $trimStrings;
        $data = $this->data;

        foreach ($data as $key => $value) {
            if (array_key_exists($key, $this->rules)) {
                if ($value === '') {
                    // convert empty strings into nulls
                    $data[$key] = null;
                }
            }

            if ($trimStrings && is_string($data[$key])) {
                $data[$key] = trim($data[$key]);
            }
        }

        $csrfParameterName = Csrf::getParameterName();

        foreach ($this->rules as $field => $rules) {
            if ($field != $csrfParameterName && !array_key_exists($field, $data)) {
                $data[$field] = null;
            }

            if (isset($data[$field]) && is_string($data[$field]) && $rules !== null) {
	            $rules = explode('|', is_object($rules) && method_exists($rules, '__toString') ? $rules->__toString() : $rules);

                // case booleans
                if (in_array('bool', $rules) || in_array('boolean', $rules)) {
                    if ($data[$field] === 'true') {
                        $data[$field] = true;
                    } else if ($data[$field] === 'false') {
                        $data[$field] = false;
                    } else {
                        $data[$field] = (bool)$data[$field];
                    }
                } else if (in_array('integer', $rules)) {
                    $data[$field] = (int)$data[$field];
                }
            }
        }

        if ($this->isCsrfEnabled() && isset($data[$csrfParameterName])) {
            unset($data[$csrfParameterName]);
        }

        return $data;
    }

    /**
     * @param bool $trimStrings
     *
     * @return \stdClass
     * @throws Config\Exception
     * @throws Exception
     */
    public function getDataObj(bool $trimStrings = null): \stdClass
    {
        $obj = new \stdClass();
        foreach ($this->getData($trimStrings ?? true) as $key => $value) {
            $obj->$key = $value;
        }

        return $obj;
    }

    /**
     * Get the list of rules where comma doesn't mean it is argument separator. These rules usually can have only one argument, so it's ok
     *
     * @return array
     */
    protected function getRulesAllowingComma(): array
    {
        return [];
    }

	/**
	 * Validate all
	 *
	 * @param bool $reValidate
	 *
	 * @return bool
	 * @throws Config\Exception
	 * @throws CsrfException
	 * @throws Exception
	 * @throws InvalidDataException
	 * @throws Security\Exception
	 * @throws ValidatorConfigException
	 */
    public function validate(bool $reValidate = false): bool
    {
        if ($this->validated !== null || $reValidate) {
            return $this->validated;
        }

        // first, validate CSRF header if set
	    $csrfHeaderName = Csrf::getHeaderName();
	    if ($this->csrfEnabled && $this->dataSource === static::DATA_SOURCE_REQUEST && $csrfHeaderName !== null) {
	    	if (!Csrf::hasTokenStored()) {
	    		Log::debug('Can not validate CSRF when CSRF token wasn\'t generated on server before this check');
	    		throw new CsrfException('CSRF token not set');
		    }

	    	if (!array_key_exists($csrfHeaderName, $_SERVER)) {
	    		Log::debug("Header {$csrfHeaderName} doesn't exist in list of \$_SERVER headers and therefore there's nothing to validate");
	    		throw new CsrfException('CSRF header not present');
		    }

	    	$csrfHeaderValue = $_SERVER[$csrfHeaderName] ?? null;

	    	if ($csrfHeaderValue === null) {
			    Log::debug("Header {$csrfHeaderName} exists, but its value is NULL");
			    throw new CsrfException('CSRF value not present');
		    }

		    $csrfHeaderValue = (string)$csrfHeaderValue;
	    	if (!Csrf::isTokenValid($csrfHeaderValue)) {
			    Log::debug('CSRF check failed - given token doesn\'t match the stored token');
			    throw new CsrfException('CSRF check failed');
		    }
	    }

        if ($this->only) {
            $requiredParameters = array_keys($this->rules);
            $gotParameters = array_keys($this->data);

            $missingRequiredParameters = array_diff($requiredParameters, $gotParameters);
            $extraParameters = array_diff($gotParameters, $requiredParameters);

            $itFailedHere = false;

            if (count($missingRequiredParameters) > 0) {
                Log::debug('Missing the following required parameters:', implode(',', $missingRequiredParameters));
                $itFailedHere = true;
            }

            if (count($extraParameters) > 0) {
                Log::debug('Got extra parameters that we don\'t need:', implode(', ', $extraParameters));
                $itFailedHere = true;
            }

            if ($itFailedHere) {
                $exception = new InvalidDataException('Invalid number of parameters were sent to server');
                $exception->setValidator($this);
                $this->validated = true;
                throw $exception;
            }
        }

        $this->valid = $this->invalid = [];

        foreach ($this->rules as $parameter => $rules) {
            if ($rules !== null) {

                if (is_array($rules)) {

                    // rules is array, so let's go through
                    // the $rules can contain several definitions (&, *, [string], [integer])
                    // & - validate whole array with these rules as well (might be min, max)
                    // * - validate each item of the data with the given rule (or rules if array)
                    // [string] - use subkey from data as assoc array and perform validation
                    // [integer] - use this validator on exactly defined position from data subset

                    // => this is "root level" of inspected array <=

                    try {
                        $value = $this->data[$parameter] ?? null;
                        $invalids = $this->testArrayWith($parameter, $rules, $value, $this->data);

                        if (count($invalids) > 0) {
                            $this->invalid[$parameter] = $invalids;
                        }

                    } catch (InvalidDataException $e) {
                        $this->invalid[$parameter] = $e->getMessage();
                    }

                } else {

                    try {
                        $value = $this->data[$parameter] ?? null;
                        $this->testDataWithRule($parameter, $rules, $value, $this->data);
                    } catch (InvalidDataException $e) {
                        $this->invalid[$parameter] = $e->getMessage();
                    }

                }
            }
        }

        $this->validated = count($this->invalid) == 0;
        return $this->validated;
    }

    /**
     * @param $parameter
     * @param array $rules
     * @param array|null $payload
     * @param array|null $context
     *
     * @return array - array of invalids
     * @throws ValidatorConfigException
     */
    private function testArrayWith($parameter, array $rules, ?array $payload, ?array $context): array
    {
        $invalids = [];

        foreach ($rules as $param => $rule) {
            if ($rule !== null) {
                if ($param === '&') {

                    if (!is_string($rule)) {
                        throw new ConfigException('When setting nested rule with "&", its value should be string. Instead, framework got ' . gettype($rule));
                    }

                    // this array must be and this:
                    $rule = "array|{$rule}";

                    // validate this whole $value object within this rule

                    try {
                        $this->testDataWithRule($parameter, $rule, $payload, $payload);
                    } catch (InvalidDataException $e) {
                        $invalids[$parameter] = $e->getMessage();
                    }

                } else if ($param === '*') {

                    if (!is_string($rule)) {
                        throw new ConfigException('When setting nested rule with "*", its value should be string. Instead, framework got ' . gettype($rule));
                    }

                    // indicates that each item of array must comply the following rule
                    foreach ($payload as $k => $v) {
                        try {
                            $this->testDataWithRule((string)$k, $rule, $v, $payload);
                        } catch (InvalidDataException $e) {
                            $invalids[$k] = $e->getMessage();
                        }
                    }

                } else if (is_integer($param) || (is_string($param) && strlen($param) > 0)) {
                    // this means that explicit array's value position is defined for validation

                    $subData = $payload[$param] ?? null;

                    // this rule can be string or array containing nested rules
                    if (is_string($rule)) {

                        try {
                            $this->testDataWithRule((string)$param, $rule, $subData, $payload);
                        } catch (InvalidDataException $e) {
                            $invalids[$param] = $e->getMessage();
                        }

                    } else if (is_array($rule)) {

                        try {
                            $subInvalids = $this->testArrayWith((string)$param, $rule, $subData, $payload);

                            if (count($subInvalids) > 0) {
                                $invalids[ctype_digit($param) ? (int)$param : $param] = $subInvalids;
                            }

                        } catch (InvalidDataException $e) {
                            $invalids[ctype_digit($param) ? (int)$param : $param] = $e->getMessage();
                        }

                    } else {
                        throw new ConfigException('When defining validator rule for the exact subitem in numeric array, you have to define it as string or array, instead, we got: ' . gettype($rule));
                    }

                } else {
                    throw new ConfigException('Invalid key in nested rules. Expected &, *, integer or non-empty string for key. Instead, framework got: ' . gettype($param));

                }
            }
        }

        return $invalids;
    }

    /**
     * Test given value on given rules
     *
     * @param mixed $parameter
     * @param string|object $rules
     * @param mixed $value
     * @param array $context
     *
     * @throws InvalidDataException
     * @throws ValidatorConfigException
     */
    protected function testDataWithRule($parameter, $rules, $value, array $context): void
    {
	    if (is_object($rules)) {
		    if (method_exists($rules, '__toString')) {
			    $rules = $rules->__toString();
		    } else {
			    throw new ConfigException('Invalid validator configuration, expected string or object with __toString(), got just object without __toString()');
		    }
	    }

        $rules = trim($rules);

        if ($rules === '') {
            throw new ConfigException("Validator parameter \"{$parameter}\" has empty string for rule. Use NULL as rule if you don't want to set anything");
        }

        $rulesWithAllowedComma = static::getRulesAllowingComma();
        $rules = explode('|', $rules);
        $stopOnFailure = false;

        for ($i = 0, $count = count($rules); $i < $count; $i++) {
            $rule = $rules[$i];

            if ($i == 0 && $rule == '!') {
                $stopOnFailure = true;
            } else {

                $args = [];
                $colonPosition = strpos($rule, ':');

                if ($colonPosition !== false) {
                    $ruleName = substr($rule, 0, $colonPosition);
                    $args = substr($rule, $colonPosition + 1);

                    if (!in_array($ruleName, $rulesWithAllowedComma)) {
                        $args = explode(',', $args);
                    } else {
                        $args = [$args];
                    }

                    $rule = $ruleName;
                }

                $method = ucfirst($rule);
                $method = "validate{$method}";

                if (!method_exists($this, $method)) {
                    throw new ValidatorConfigException("Trying to use invalid validation rule={$rule}");
                }

                $testResult = $this->$method($value, $parameter, $args, $rules, $context);

                if ($testResult === null) {
                    // do nothing, because it's good

                } else if (is_string($testResult)) {
                    throw new InvalidDataException($testResult);

                } else {
                    $type = gettype($testResult);
                    $class = get_class($this);
                    throw new ValidatorConfigException("Invalid test result returned from {$class}->{$method}({$parameter}); expected TRUE or string, got {$type}");
                }
            }
        }
    }

    /**
     * @return bool
     * @throws InvalidDataException
     * @throws ValidatorConfigException
     */
    public function isAllValid(): bool
    {
        $this->validate();
        return count($this->invalid) == 0;
    }

    /**
     * Get error messages
     *
     * @return array
     * @throws InvalidDataException
     * @throws ValidatorConfigException
     */
    public function getMessages(): array
    {
        $this->validate();
        return $this->invalid;
    }

    /**
     * Validate if parameter is present in array data. It will fail if parameter name does not exists
     * within data being validated.
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rule
     * @param array $context
     *
     * @return string|null
     * @throws ValidatorConfigException
     * @example 'param' => 'present' - will fail if 'param' is not within validation data
     */
    protected function validatePresent($value, string $parameter, array $args = [], ?array $rules, array $context): ?string
    {
        if (!array_key_exists($parameter, $context)) {
            return Message::getMessage(Message::PRESENT, [
                'param' => $parameter
            ]);
        }

        return null;
    }

    /**
     * Validate if passed parameter has any value. It will fail if parameter does not exists within
     * data being validated or if passed value is empty string or if it's null (not string 'null', but real null for some reason)
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rule
     * @param array $context
     *
     * @return string|null
     * @throws ValidatorConfigException
     * @example 'param' => 'required'
     */
    protected function validateRequired($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        //$value = $this->getValue($parameter);

        if ($value === null) {
            return Message::getMessage(Message::REQUIRED, [
                'param' => $parameter
            ]);
        }

        if (is_numeric($value)) { // should be all good
            return null;
        }

        if (is_string($value)) {
            if (trim($value) == '') {
                return Message::getMessage(Message::REQUIRED, [
                    'param' => $parameter,
                    'value' => ''
                ]);
            } else {
                return null;
            }
        }

        if (is_array($value)) {
            // array can not be empty
            if (count($value) == 0) {
                return Message::getMessage(Message::REQUIRED, [
                    'param' => $parameter
                ]);
            } else {
                return null;
            }
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $parameter
            ]);
        }

        return null;
    }

    /**
     * Validate if value has the minimum value of
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rules
     * @param array|null $context
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example 'param' => 'min:5' - will fail if value is not at least 5
     */
    protected function validateMin($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        if (count($args) == 0) {
            throw new ValidatorConfigException('Validator \'min\' has to have defined minimum value');
        }

        $minSize = $args[0] ?? null;

        if (!is_numeric($minSize)) {
            throw new ValidatorConfigException('Validator \'min\' has non-numeric value');
        }

        $minSize = (int)$minSize;

        //$value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (is_array($value) && in_array('array', $rules)) {
            // we got an array, so let's check the array size

            if (count($value) >= $minSize) {
                return null;
            }

            return Message::getMessage(Message::MIN_VALUE, [
                'param' => $parameter,
                'min' => $minSize,
                'value' => $value
            ]);
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $parameter
            ]);
        }

        $value = (string)$value;

        if ($value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return Message::getMessage(Message::NUMERIC, [
                'param' => $parameter,
                'value' => $value
            ]);
        }

        $value += 0;

        if ($value < $minSize) {
            return Message::getMessage(Message::MIN_VALUE, [
                'param' => $parameter,
                'min' => $minSize,
                'value' => $value
            ]);
        }

        return null;
    }

    /**
     * Validate if value has the maximum value of
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rules
     * @param array|null $context
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example 'param' => 'max:5' - will fail if value is not at least 5
     */
    protected function validateMax($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        if (count($args) == 0) {
            throw new ValidatorConfigException('Validator \'max\' has to have defined maximum value');
        }

        $maxSize = $args[0] ?? null;

        if (!is_numeric($maxSize)) {
            throw new ValidatorConfigException('Validator \'max\' has non-numeric value');
        }

        $maxSize = (int)$maxSize;

        //$value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (is_array($value) && in_array('array', $rules)) {
            if (count($value) > $maxSize) {
                return Message::getMessage(Message::MAX_VALUE, [
                    'param' => $parameter,
                    'max' => $maxSize,
                    'value' => ''
                ]);
            }

            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $parameter
            ]);
        }

        $value = (string)$value;

        if ($value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return Message::getMessage(Message::NUMERIC, [
                'param' => $parameter,
                'value' => $value
            ]);
        }

        $value += 0;

        if ($value > $maxSize) {
            return Message::getMessage(Message::MAX_VALUE, [
                'param' => $parameter,
                'max' => $maxSize,
                'value' => $value
            ]);
        }

        return null;
    }

    /**
     * Validate minimum length of value. If value is numeric, it'll be converted to string
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rules
     * @param array|null $context
     *
     * @return null|string
     * @throws ValidatorConfigException
     *
     * @example 'param' => 'minLength:5'
     * @throws Exception
     * @deprecated due to ability to use $rules
     */
    protected function validateMinLength($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        if (count($args) == 0) {
            throw new ValidatorConfigException('Validator \'minLength\' has to have defined minimum value');
        }

        if (!is_numeric($args[0])) {
            throw new ValidatorConfigException('Validator \'minLength\' has non-numeric value');
        }

        //$value = $this->getValue($parameter);

        $minLength = (int)$args[0];

        if ($minLength < 0) {
            throw new ValidatorConfigException('Validator \'minLength\' can not have negative minimum length');
        }

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value, Application::getEncoding()) < $minLength) {
            return Message::getMessage(Message::MIN_LENGTH, [
                'param' => $parameter,
                'min' => $minLength,
                'value' => $value
            ]);
        }

        return null;
    }

    /**
     * Validate maximum length of value. If value is numeric, it'll be converted to string
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rules
     * @param array|null $context
     *
     * @return null|string
     * @throws ValidatorConfigException
     *
     * @example 'param' => 'maxLength:5'
     * @throws Exception
     * @deprecated due to ability to use $rules
     */
    protected function validateMaxLength($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        if (count($args) == 0) {
            throw new ValidatorConfigException('Validator \'maxLength\' has to have defined maximum value');
        }

        if (!is_numeric($args[0])) {
            throw new ValidatorConfigException('Validator \'maxLength\' has non-numeric value');
        }

        //$value = $this->getValue($parameter);

        $maxLength = (int)$args[0];
        if ($maxLength <= 0) {
            throw new ValidatorConfigException('Validator \'maxLength\' can\'t have value less or equal to zero in its definition');
        }

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value, Application::getEncoding()) > $maxLength) {
            return Message::getMessage(Message::MAX_LENGTH, [
                'param' => $parameter,
                'max' => $maxLength,
                'value' => $value
            ]);
        }

        return null;
    }

    /**
     * Validate the exact length of string
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rules
     * @param array|null $context
     *
     * @return null|string
     * @throws ValidatorConfigException
     *
     * @example 'param' => 'length:5'
     * @throws Exception
     */
    protected function validateLength($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        if (count($args) == 0) {
            throw new ValidatorConfigException('Validator \'length\' has to have defined maximum value');
        }

        if (!is_numeric($args[0])) {
            throw new ValidatorConfigException('Validator \'length\' has non-numeric value');
        }

        $requiredLength = (int)$args[0];

        if ($requiredLength < 0) {
            throw new ValidatorConfigException('Validator \'length\' has negative value in its definition which is not allowed');
        }

        //$value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value, Application::getEncoding()) != $requiredLength) {
            return Message::getMessage(Message::LENGTH, [
                'param' => $parameter,
                'length' => $args[0],
                'value' => $value
            ]);
        }

        return null;
    }

    /**
     * Validate if given value is integer. If you need to validate min and max, then chain those validators
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rules
     * @param array|null $context
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example 'param' => 'integer' - passed value must contain 0-9 digits only
     */
    protected function validateInteger($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        //$value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $parameter
            ]);
        }

        $value = (string)$value;

        if ($value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return Message::getMessage(Message::NUMERIC, [
                'param' => $parameter,
                'value' => $value
            ]);
        }

        if ($value[0] == '-') {
            $value = substr($value, 1);
        }

        if (!ctype_digit($value)) {
            return Message::getMessage(Message::INTEGER, [
                'param' => $parameter,
                'value' => $value
            ]);
        }

        return null;
    }

    /**
     * Validate if given value is boolean
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rules
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example 'param' => 'bool' - passed value must be boolean
     */
    protected function validateBool($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        //$value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $parameter
            ]);
        }

        if ($value === '') {
            return null;
        }

        if (!is_bool($value) && $value !== 'true' && $value !== 'false') {
            return Message::getMessage(Message::BOOL, [
                'param' => $parameter
            ]);
        }

        return null;
    }

    /**
     * Validate if given value is boolean
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rules
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example 'param' => 'boolean' - passed value must be boolean
     * @see validateBool()
     */
    protected function validateBoolean($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        return $this->validateBool($value, $parameter, $args);
    }

    /**
     * Validate if given value is numeric or not (using PHP's is_numeric()), so it allows decimals
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rules
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example 'param' => 'numeric'
     */
    protected function validateNumeric($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        //$value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $parameter
            ]);
        }

        $value = (string)$value;

        if ($value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return Message::getMessage(Message::NUMERIC, [
                'param' => $parameter,
                'value' => $value
            ]);
        }

        return null;
    }

    /**
     * Validate if given value is alpha numeric or not, allowing lower and uppercase English letters with numbers
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rules
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example 'param' => 'alphaNum'
     */
    protected function validateAlphaNum($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        //$value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $parameter
            ]);
        }

        $value = (string)$value;

        if ($value === '') {
            return null;
        }

        if (!ctype_alnum($value)) {
            return Message::getMessage(Message::ALPHA_NUM, [
                'param' => $parameter,
                'value' => $value
            ]);
        }

        return null;
    }

    /**
     * Validate if given value is hexadecimal number or not
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rules
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example 'param' => 'hex'
     */
    protected function validateHex($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        //$value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value === '') {
            return null;
        }

        if ($value[0] == '-') {
            $value = substr($value, 1);
        }

        if (!ctype_xdigit($value)) {
            return Message::getMessage(Message::HEX, [
                'param' => $parameter,
                'value' => $value
            ]);
        }

        return null;
    }

    /**
     * Validate if given value contains alpha chars only or not, allowing lower and uppercase English letters
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rules
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example 'param' => 'alpha'
     */
    protected function validateAlpha($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        //$value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value === '') {
            return null;
        }

        if (!ctype_alpha($value)) {
            return Message::getMessage(Message::ALPHA, [
                'param' => $parameter,
                'value' => $value
            ]);
        }

        return null;
    }

    /**
     * Validate if given value is valid email address by syntax
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rules
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example 'param' => 'email'
     */
    protected function validateEmail($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        //$value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value === '') {
            return null;
        }

        if (!Validate::isEmail($value)) {
            return Message::getMessage(Message::EMAIL, [
                'param' => $parameter,
                'value' => $value
            ]);
        }

        return null;
    }

    /**
     * Validate if given value is valid URL slug
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rules
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example 'param' => 'slug'
     */
    protected function validateSlug($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        //$value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value === '') {
            return null;
        }

        if (!Validate::isSlug($value)) {
            return Message::getMessage(Message::SLUG, [
                'param' => $parameter,
                'value' => $value
            ]);
        }

        return null;
    }

    /**
     * Validate if given parameter has required value
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rules
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example 'param' => 'is:yes'
     * @example 'param' => 'is:500'
     */
    protected function validateIs($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        if (count($args) == 0) {
            throw new ValidatorConfigException("Validator 'is' must have argument in validator list for parameter {$parameter}");
        }

        //$value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value === '') {
            return null;
        }

        if ($value !== $args[0]) {
            return Message::getMessage(Message::IS, [
                'param' => $parameter,
                'value' => $value
            ]);
        }

        return null;
    }

    /**
     * Validate if given value has equal or less number of required decimals
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rules
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example 'param' => 'decimal:2'
     */
    protected function validateDecimal($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        if (count($args) == 0) {
            throw new ValidatorConfigException("Validator 'decimal' must have argument in validator list for parameter {$parameter}");
        }

        $decimals = (int)$args[0];

        if ($decimals <= 0) {
            throw new ValidatorConfigException("Validator 'decimal' has invalid value which is lower or equal to zero; for parameter={$parameter}");
        }

        if ($decimals > 100) {
            throw new ValidatorConfigException("Validator 'decimal' can't have that large argument; parameter={$parameter}");
        }

        //$value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return Message::getMessage(Message::NUMERIC, [
                'param' => $parameter,
                'value' => $value
            ]);
        }

        $parts = explode('.', $value);

        if (count($parts) == 1) {
            return null; // value is not decimal, it's probably integer, so it's ok
        }

        $valueDecimals = $parts[1];

        if (strlen($valueDecimals) > $decimals) {
            return Message::getMessage(Message::DECIMAL, [
                'param' => $parameter,
                'value' => $value,
                'decimals' => $decimals
            ]);
        }

        return null;
    }

    /**
     * Validate if value is the same as value in other field
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rules
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example
     *  'param1' => 'required',
     *  'param2' => 'same:param1'
     */
    protected function validateSame($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        if (count($args) == 0) {
            throw new ValidatorConfigException("Validator 'same' must have argument in validator list for parameter {$parameter}");
        }

        $sameAsField = trim($args[0]);

        if (strlen($sameAsField) == 0) {
            throw new ValidatorConfigException("Validator 'same' must have non-empty argument; parameter={$parameter}");
        }

        $present = $this->validatePresent($value, $sameAsField, [], $rules, $context);
        if ($present !== null) {
            return $present;
        }

        //$value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $parameter
            ]);
        }

        $sameAsValue = $context[$sameAsField] ?? null;

        if (!is_scalar($sameAsValue)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $sameAsField
            ]);
        }

        if ($value != $sameAsValue) {
            return Message::getMessage(Message::SAME, [
                'param' => $parameter,
                'value' => $value,
                'otherField' => $sameAsField,
                'otherValue' => $sameAsValue
            ]);
        }

        return null;
    }

    /**
     * Opposite of "same", passes if value is different then value in other field
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rules
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example
     *  'param1' => 'required',
     *  'param2' => 'different:param1'
     */
    protected function validateDifferent($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        if (count($args) == 0) {
            throw new ValidatorConfigException("Validator 'different' must have argument in validator list for parameter {$parameter}");
        }

        $differentAsField = trim($args[0]);

        if (strlen($differentAsField) == 0) {
            throw new ValidatorConfigException("Validator 'different' must have non-empty argument; parameter={$parameter}");
        }

	    $present = $this->validatePresent($value, $differentAsField, [], $rules, $context);
        if ($present !== null) {
            return $present;
        }

        //$value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $parameter
            ]);
        }

        $differentAsValue = $context[$differentAsField] ?? null;

        if (!is_scalar($differentAsValue)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $differentAsField
            ]);
        }

        if ($value == $differentAsValue) {
            return Message::getMessage(Message::DIFFERENT, [
                'param' => $parameter,
                'value' => $value,
                'otherField' => $differentAsField,
                'otherValue' => $differentAsValue
            ]);
        }

        return null;
    }

    /**
     * Validate if given value is properly formatted date
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rules
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example 'param' => 'date'
     * @example 'param' => 'date:Y-m-d'
     */
    protected function validateDate($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        $format = $args[0] ?? null;

        if ($format === '') {
            throw new ValidatorConfigException("Invalid format specified in 'date' validator for parameter {$parameter}");
        }

        //$value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value === '') {
            return null;
        }

        if ($format === null) {
            // no format, use simple strtotime()
            if (strtotime($value) === false) {
                return Message::getMessage(Message::DATE, [
                    'param' => $parameter,
                    'value' => $value
                ]);
            }
        } else {
            $dateTime = \DateTime::createFromFormat($format, $value);
            if ($dateTime === false || $dateTime->format($format) !== $value) {
                return Message::getMessage(Message::DATE, [
                    'param' => $parameter,
                    'value' => $value
                ]);
            }
        }

        return null;
    }

    /**
     * Validate if value is any of allowed values
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rules
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example 'param' => 'anyOf:one,two,3,four'
     * @example 'param' => 'anyOf:one,two%2C maybe three' // if comma needs to be used, then urlencode it
     */
    protected function validateAnyOf($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        if (count($args) == 0) {
            throw new ValidatorConfigException("Validator 'anyOf' must have at least one defined value; parameter={$parameter}");
        }

        if ($args[0] == '') {
            throw new ValidatorConfigException("Validator 'anyOf' must have non-empty string for argument; parameter={$parameter}");
        }

        foreach ($args as $i => $arg) {
            $args[$i] = urldecode(trim($arg));
        }

        //$value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value === '') {
            return null;
        }

        if (!in_array($value, $args)) {
            return Message::getMessage(Message::ANY_OF, [
                'param' => $parameter,
                'value' => $value,
                'allowedValues' => implode(', ', $args)
            ]);
        }

        return null;
    }

    /**
     * Return error if value is not unique in database
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rules (Class\Name,uniqueField[,exceptionValue][,exceptionField])
     *
     * @throws Exception
     * @return true|string
     * @example 'email' => 'email|unique:\Db\User,email,my@email.com'
     * @example
     * 'id' => 'required|integer|min:1',
     * 'email' => 'email|unique:\Db\User,email,field:id,id' // check if email exists in \Db\User model, but exclude ID with value from param 'id'
     */
    protected function validateUnique($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        if (count($args) < 2) {
            throw new ValidatorConfigException("Validator 'unique' must have at least two defined arguments; parameter={$parameter}");
        }

        array_walk($args, 'trim');

        if ($args[0] == '') {
            throw new ValidatorConfigException("Validator 'unique' must have non-empty string for argument=0; parameter={$parameter}");
        }

        //$value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value === '') {
            return null;
        }

        for ($i = count($args); $i < 4; $i++) {
            if (!isset($args[$i])) {
                $args[$i] = null;
            }
        }

        /** @var Model $modelClass */
        list($modelClass, $uniqueField, $exceptionValue, $exceptionField) = $args;

        if (is_string($exceptionValue)) {
            if (substr($exceptionValue, 0, 6) == 'field:') {
                $exceptionFieldName = substr($exceptionValue, 6);
                $exceptionFieldValue = $context[$exceptionFieldName] ?? null;

                if ($exceptionFieldValue === null) {
                    throw new ValidatorConfigException("Can not validate unique parameter={$parameter} when exception field name={$exceptionFieldName} is not present or has the value of null");
                }

                $exceptionValue = $exceptionFieldValue;
            } else {
                $exceptionValue = urldecode(trim($exceptionValue));
            }
        }

        if (!$modelClass::isUnique($uniqueField, $value, $exceptionValue, $exceptionField)) {
            return Message::getMessage(Message::NOT_UNIQUE, [
                'param' => $parameter,
                'value' => $value,
                'exceptionField' => $exceptionField,
                'exceptionValue' => $exceptionValue
            ]);
        }

        return null;
    }

    /**
     * Return error if value does not exists in database
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rules (Class\Name,fieldName)
     *
     * @throws Exception
     * @return true|string
     * @example 'user_id' => 'required|integer|exists:\Db\User,id' // e.g. user_id = 5, so this will check if there is record in \Db\User model under id=5
     */
    protected function validateExists($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        if (count($args) < 2) {
            throw new ValidatorConfigException("Validator 'exists' must have at least two defined arguments; parameter={$parameter}");
        }

        array_walk($args, 'trim');

        if ($args[0] == '') {
            throw new ValidatorConfigException("Validator 'exists' must have non-empty string for argument=0; parameter={$parameter}");
        }

        //$value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value === '') {
            return null;
        }

        /** @var Model $modelClass */
        list($modelClass, $queryField) = $args;

        if ($modelClass::count([$queryField => $value]) == 0) {
            return Message::getMessage(Message::NO_RECORD, [
                'param' => $parameter,
                'value' => $value,
                'field' => $queryField
            ]);
        }

        return null;
    }

    /**
     * Validate if given value has valid CSRF token
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rules
     * @param array|null $context
     *
     * @return null|string
     * @throws Config\Exception
     * @throws Exception
     * @throws Security\Exception
     * @throws ValidatorConfigException
     * @example 'csrf_token' => 'csrf'
     */
    protected function validateCsrf($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        //$value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if (Csrf::isTokenValid($value)) {
            return null;
        }

        if (!in_array($value, $args)) {
            return Message::getMessage(Message::CSRF_FAILED, [
                'param' => $parameter
            ]);
        }

        return null;
    }

    /**
     * Validate if given parameter has required value
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rules
     * @param array|null $context
     *
     * @return null|string
     * @throws Exception
     * @throws ValidatorConfigException
     * @example 'param' => 'startsWith:098'
     */
    protected function validateStartsWith($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        if (count($args) == 0) {
            throw new ValidatorConfigException("Validator 'startsWith' must have argument in validator list for parameter {$parameter}");
        }

        $startsWith = $args[0] ?? '';

        if (strlen($startsWith) == 0) {
            throw new ValidatorConfigException("Validator 'startsWith' have empty argument in validator list for parameter {$parameter}");
        }

        //$value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $parameter
            ]);
        }

        $value = (string)$value;

        if ($value === '') {
            return null;
        }

        if (!Util::startsWith($value, $startsWith)) {
            return Message::getMessage(Message::STARTS_WITH, [
            	'startsWith' => $startsWith,
                'param' => $parameter,
                'value' => $value
            ]);
        }

        return null;
    }

    /**
     * Validate if given parameter has required value
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rules
     *
     * @return null|string
     * @throws Exception
     * @throws ValidatorConfigException
     * @example 'param' => 'endsWith:dd1'
     */
    protected function validateEndsWith($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        if (count($args) == 0) {
            throw new ValidatorConfigException("Validator 'endsWith' must have argument in validator list for parameter {$parameter}");
        }

        $endsWith = $args[0] ?? '';

        if (strlen($endsWith) == 0) {
            throw new ValidatorConfigException("Validator 'endsWith' have empty argument in validator list for parameter {$parameter}");
        }

        //$value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
                'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value === '') {
            return null;
        }

        if (!Util::endsWith($value, $endsWith)) {
            return Message::getMessage(Message::ENDS_WITH, [
            	'endsWith' => $endsWith,
                'param' => $parameter,
                'value' => $value
            ]);
        }

        return null;
    }

    /**
     * Validate if given value is PHP array
     *
     * @param mixed $value
     * @param string $parameter
     * @param array $args
     * @param array $rules other rules
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example 'param' => 'array'
     * @example 'param' => 'array:5'
     */
    protected function validateArray($value, string $parameter, array $args = [], array $rules = null, array $context = null): ?string
    {
        $count = $args[0] ?? null;

        if ($count !== null) {
            if ($count == '' || !is_numeric($count)) {
                throw new ValidatorConfigException("Invalid array count definition for parameter {$parameter}");
            }

            $count = (int)$count;

            if ($count < 0) {
                throw new ValidatorConfigException("Invalid array count definition for parameter {$parameter}, argument can not be negative");
            }
        }

        //$value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (is_string($value) && $value === '') {
            return null;
        }

        if (!is_array($value)) {
            return Message::getMessage(Message::IS_NOT_ARRAY, [
                'param' => $parameter
            ]);
        }

        if ($count !== null) {
            $currentCount = count($value);

            if ($currentCount != $count) {
                if (ctype_digit($parameter)) {
                    $parameter = "Element on position {$parameter}";
                }

                return Message::getMessage(Message::ARRAY_WRONG_COUNT, [
                    'param' => $parameter,
                    'requiredCount' => $count,
                    'currentCount' => $currentCount
                ]);
            }
        }

        return null;
    }

}
