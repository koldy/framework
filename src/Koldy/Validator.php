<?php declare(strict_types = 1);

namespace Koldy;

use Koldy\Db\Model;
use Koldy\Security\Csrf;
use Koldy\Validator\{
  Exception as InvalidDataException, ConfigException as ValidatorConfigException, Message, Validate
};

class Validator
{

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
     */
    public function __construct(array $rules, array $data = null)
    {
        if ($data === null) {
            $this->data = Request::getAllParameters();
            $this->csrfEnabled = Csrf::isEnabled();

            if ($this->csrfEnabled && !isset($rules[Csrf::getParameterName()])) {
                $rules[Csrf::getParameterName()] = 'required|csrf';
            }
        } else {
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
     * @throws Validator\Exception
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
     * @throws Validator\Exception
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
     */
    public function isCsrfEnabled(): bool
    {
        return $this->csrfEnabled;
    }

    /**
     * Manually enable CSRF check. This has to be enabled manually if you're passing
     * custom data to validator and still want to do CSRF check.
     */
    public function enableCsrfCheck(): void
    {
        $this->csrfEnabled = true;
    }

    /**
     * You might want to disable automatic CSRF check in some cases.
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
                $rules = explode('|', $rules);

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
     */
    public function getDataObj(bool $trimStrings = null): \stdClass
    {
        $trimStrings = ($trimStrings === null) ? true : $trimStrings;
        $obj = new \stdClass();
        foreach ($this->getData() as $key => $value) {
            if ($trimStrings) {
                $obj->$key = is_string($value) ? trim($value) : $value;
            } else {
                $obj->$key = $value;
            }
        }

        return $obj;
    }

    /**
     * Get the value for needed parameter
     *
     * @param string $parameter
     *
     * @return mixed|null
     */
    protected function getValue(string $parameter)
    {
        return $this->data[$parameter] ?? null;
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
     * @throws InvalidDataException
     * @throws ValidatorConfigException
     */
    public function validate(bool $reValidate = false): bool
    {
        if ($this->validated !== null || $reValidate) {
            return $this->validated;
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
        $rulesWithAllowedComma = static::getRulesAllowingComma();

        foreach ($this->rules as $parameter => $rules) {
            if ($rules !== null) {
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

                        $testResult = $this->$method($parameter, $args);

                        if ($testResult === null) {
                            $this->valid[] = $parameter;

                        } else if (is_string($testResult)) {
                            $this->invalid[$parameter] = $testResult;

                        } else {
                            $type = gettype($testResult);
                            $class = get_class($this);
                            throw new ValidatorConfigException("Invalid test result returned from {$class}->{$method}({$parameter}); expected TRUE or string, got {$type}");

                        }
                    }
                }
            }
        }

        $this->validated = count($this->invalid) == 0;
        return $this->validated;
    }

    /**
     * @return bool
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
     * @param string $parameter
     * @param array $args
     *
     * @return string|null
     * @example 'param' => 'present' - will fail if 'param' is not within validation data
     */
    protected function validatePresent(string $parameter, array $args = []): ?string
    {
        $value = $this->getValue($parameter);

        if ($value === null) {
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
     * @param string $parameter
     * @param array $args
     *
     * @return string|null
     * @example 'param' => 'required'
     */
    protected function validateRequired(string $parameter, array $args = []): ?string
    {
        $value = $this->getValue($parameter);

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
     * @param string $parameter
     * @param array $args
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example 'param' => 'min:5' - will fail if value is not at least 5
     */
    protected function validateMin(string $parameter, array $args = []): ?string
    {
        if (count($args) == 0) {
            throw new ValidatorConfigException('Validator \'min\' has to have defined minimum value');
        }

        if (!is_numeric($args[0])) {
            throw new ValidatorConfigException('Validator \'min\' has non-numeric value');
        }

        $value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
              'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value == '') {
            return null;
        }

        if (!is_numeric($value)) {
            return Message::getMessage(Message::NUMERIC, [
              'param' => $parameter,
              'value' => $value
            ]);
        }

        $value += 0;

        if ($value < $args[0]) {
            return Message::getMessage(Message::MIN_VALUE, [
              'param' => $parameter,
              'min' => $args[0],
              'value' => $value
            ]);
        }

        return null;
    }

    /**
     * Validate if value has the maximum value of
     *
     * @param string $parameter
     * @param array $args
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example 'param' => 'max:5' - will fail if value is not at least 5
     */
    protected function validateMax(string $parameter, array $args = []): ?string
    {
        if (count($args) == 0) {
            throw new ValidatorConfigException('Validator \'max\' has to have defined maximum value');
        }

        if (!is_numeric($args[0])) {
            throw new ValidatorConfigException('Validator \'max\' has non-numeric value');
        }

        $value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
              'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value == '') {
            return null;
        }

        if (!is_numeric($value)) {
            return Message::getMessage(Message::NUMERIC, [
              'param' => $parameter,
              'value' => $value
            ]);
        }

        $value += 0;

        if ($value > $args[0]) {
            return Message::getMessage(Message::MAX_VALUE, [
              'param' => $parameter,
              'max' => $args[0]
            ]);
        }

        return null;
    }

    /**
     * Validate minimum length of value. If value is numeric, it'll be converted to string
     *
     * @param string $parameter
     * @param array $args
     *
     * @return null|string
     * @throws ValidatorConfigException
     *
     * @example 'param' => 'minLength:5'
     */
    protected function validateMinLength(string $parameter, array $args = []): ?string
    {
        if (count($args) == 0) {
            throw new ValidatorConfigException('Validator \'minLength\' has to have defined minimum value');
        }

        if (!is_numeric($args[0])) {
            throw new ValidatorConfigException('Validator \'minLength\' has non-numeric value');
        }

        $value = $this->getValue($parameter);

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

        if (strlen($value) < (int)$args[0]) {
            return Message::getMessage(Message::MIN_LENGTH, [
              'param' => $parameter,
              'min' => $args[0],
              'value' => $value
            ]);
        }

        return null;
    }

    /**
     * Validate maximum length of value. If value is numeric, it'll be converted to string
     *
     * @param string $parameter
     * @param array $args
     *
     * @return null|string
     * @throws ValidatorConfigException
     *
     * @example 'param' => 'maxLength:5'
     */
    protected function validateMaxLength(string $parameter, array $args = []): ?string
    {
        if (count($args) == 0) {
            throw new ValidatorConfigException('Validator \'maxLength\' has to have defined maximum value');
        }

        if (!is_numeric($args[0])) {
            throw new ValidatorConfigException('Validator \'maxLength\' has non-numeric value');
        }

        $value = $this->getValue($parameter);

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

        if (strlen($value) > (int)$args[0]) {
            return Message::getMessage(Message::MAX_LENGTH, [
              'param' => $parameter,
              'max' => $args[0],
              'value' => $value
            ]);
        }

        return null;
    }

    /**
     * Validate the exact length of string
     *
     * @param string $parameter
     * @param array $args
     *
     * @return null|string
     * @throws ValidatorConfigException
     *
     * @example 'param' => 'length:5'
     */
    protected function validateLength(string $parameter, array $args = []): ?string
    {
        if (count($args) == 0) {
            throw new ValidatorConfigException('Validator \'length\' has to have defined maximum value');
        }

        if (!is_numeric($args[0])) {
            throw new ValidatorConfigException('Validator \'length\' has non-numeric value');
        }

        $value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
              'param' => $parameter
            ]);
        }

        $value = (string)$value;

        if (strlen($value) != (int)$args[0]) {
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
     * @param string $parameter
     * @param array $args
     *
     * @return null|string
     * @example 'param' => 'integer' - passed value must contain 0-9 digits only
     */
    protected function validateInteger(string $parameter, array $args = []): ?string
    {
        $value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
              'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value == '') {
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
     * @param string $parameter
     * @param array $args
     *
     * @return null|string
     * @example 'param' => 'bool' - passed value must be boolean
     */
    protected function validateBool(string $parameter, array $args = []): ?string
    {
        $value = $this->getValue($parameter);

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
     * @param string $parameter
     * @param array $args
     *
     * @return null|string
     * @example 'param' => 'boolean' - passed value must be boolean
     */
    protected function validateBoolean(string $parameter, array $args = []): ?string
    {
        return $this->validateBool($parameter, $args);
    }

    /**
     * Validate if given value is numeric or not (using PHP's is_numeric()), so it allows decimals
     *
     * @param string $parameter
     * @param array $args
     *
     * @return null|string
     * @example 'param' => 'numeric'
     */
    protected function validateNumeric(string $parameter, array $args = []): ?string
    {
        $value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
              'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value == '') {
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
     * @param string $parameter
     * @param array $args
     *
     * @return null|string
     * @example 'param' => 'alphaNum'
     */
    protected function validateAlphaNum(string $parameter, array $args = []): ?string
    {
        $value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
              'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value == '') {
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
     * @param string $parameter
     * @param array $args
     *
     * @return null|string
     * @example 'param' => 'hex'
     */
    protected function validateHex(string $parameter, array $args = []): ?string
    {
        $value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
              'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value == '') {
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
     * @param string $parameter
     * @param array $args
     *
     * @return null|string
     * @example 'param' => 'alpha'
     */
    protected function validateAlpha(string $parameter, array $args = []): ?string
    {
        $value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
              'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value == '') {
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
     * @param string $parameter
     * @param array $args
     *
     * @return null|string
     * @example 'param' => 'email'
     */
    protected function validateEmail(string $parameter, array $args = []): ?string
    {
        $value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
              'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value == '') {
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
     * @param string $parameter
     * @param array $args
     *
     * @return null|string
     * @example 'param' => 'slug'
     */
    protected function validateSlug(string $parameter, array $args = []): ?string
    {
        $value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
              'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value == '') {
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
     * @param string $parameter
     * @param array $args
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example 'param' => 'is:yes'
     * @example 'param' => 'is:500'
     */
    protected function validateIs(string $parameter, array $args = []): ?string
    {
        if (count($args) == 0) {
            throw new ValidatorConfigException("Validator 'is' must have argument in validator list for parameter {$parameter}");
        }

        $value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
              'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value == '') {
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
     * @param string $parameter
     * @param array $args
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example 'param' => 'decimal:2'
     */
    protected function validateDecimal(string $parameter, array $args = []): ?string
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

        $value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
              'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value == '') {
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
     * @param string $parameter
     * @param array $args
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example
     *  'param1' => 'required',
     *  'param2' => 'same:param1'
     */
    protected function validateSame(string $parameter, array $args = []): ?string
    {
        if (count($args) == 0) {
            throw new ValidatorConfigException("Validator 'same' must have argument in validator list for parameter {$parameter}");
        }

        $sameAsField = trim($args[0]);

        if (strlen($sameAsField) == 0) {
            throw new ValidatorConfigException("Validator 'same' must have non-empty argument; parameter={$parameter}");
        }

        $present = $this->validatePresent($sameAsField);
        if ($present !== null) {
            return $present;
        }

        $value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
              'param' => $parameter
            ]);
        }

        $sameAsValue = $this->getValue($sameAsField);

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
     * @param string $parameter
     * @param array $args
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example
     *  'param1' => 'required',
     *  'param2' => 'different:param1'
     */
    protected function validateDifferent(string $parameter, array $args = []): ?string
    {
        if (count($args) == 0) {
            throw new ValidatorConfigException("Validator 'different' must have argument in validator list for parameter {$parameter}");
        }

        $differentAsField = trim($args[0]);

        if (strlen($differentAsField) == 0) {
            throw new ValidatorConfigException("Validator 'different' must have non-empty argument; parameter={$parameter}");
        }

        $present = $this->validatePresent($differentAsField);
        if ($present !== null) {
            return $present;
        }

        $value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
              'param' => $parameter
            ]);
        }

        $differentAsValue = $this->getValue($differentAsField);

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
     * @param string $parameter
     * @param array $args
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example 'param' => 'date'
     * @example 'param' => 'date:Y-m-d'
     */
    protected function validateDate(string $parameter, array $args = []): ?string
    {
        $format = $args[0] ?? null;

        if ($format === '') {
            throw new ValidatorConfigException("Invalid format specified in 'date' validator for parameter {$parameter}");
        }

        $value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
              'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value == '') {
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
     * Validate if given value is PHP array
     *
     * @param string $parameter
     * @param array $args
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example 'param' => 'array'
     * @example 'param' => 'array:5'
     */
    protected function validateArray(string $parameter, array $args = []): ?string
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

        $value = $this->getValue($parameter);

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
                return Message::getMessage(Message::ARRAY_WRONG_COUNT, [
                  'param' => $parameter,
                  'requiredCount' => $count,
                  'currentCount' => $currentCount
                ]);
            }
        }

        return null;
    }

    /**
     * Validate if value is any of allowed values
     *
     * @param string $parameter
     * @param array $args
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example 'param' => 'anyOf:one,two,3,four'
     * @example 'param' => 'anyOf:one,two%2C maybe three' // if comma needs to be used, then urlencode it
     */
    protected function validateAnyOf(string $parameter, array $args = []): ?string
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

        $value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
              'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value == '') {
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
     * @param string $parameter
     * @param array $args (Class\Name,uniqueField[,exceptionValue][,exceptionField])
     *
     * @throws Exception
     * @return true|string
     * @example 'email' => 'email|unique:\Db\User,email,my@email.com'
     * @example
     * 'id' => 'required|integer|min:1',
     * 'email' => 'email|unique:\Db\User,email,field:id,id' // check if email exists in \Db\User model, but exclude ID with value from param 'id'
     */
    protected function validateUnique(string $parameter, array $args = []): ?string
    {
        if (count($args) < 2) {
            throw new ValidatorConfigException("Validator 'unique' must have at least two defined arguments; parameter={$parameter}");
        }

        array_walk($args, 'trim');

        if ($args[0] == '') {
            throw new ValidatorConfigException("Validator 'unique' must have non-empty string for argument=0; parameter={$parameter}");
        }

        $value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
              'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value == '') {
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
                $exceptionFieldValue = $this->getValue($exceptionFieldName);

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
     * @param string $parameter
     * @param array $args (Class\Name,fieldName)
     *
     * @throws Exception
     * @return true|string
     * @example 'user_id' => 'required|integer|exists:\Db\User,id' // e.g. user_id = 5, so this will check if there is record in \Db\User model under id=5
     */
    protected function validateExists(string $parameter, array $args = []): ?string
    {
        if (count($args) < 2) {
            throw new ValidatorConfigException("Validator 'exists' must have at least two defined arguments; parameter={$parameter}");
        }

        array_walk($args, 'trim');

        if ($args[0] == '') {
            throw new ValidatorConfigException("Validator 'exists' must have non-empty string for argument=0; parameter={$parameter}");
        }

        $value = $this->getValue($parameter);

        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return Message::getMessage(Message::PRIMITIVE, [
              'param' => $parameter
            ]);
        }

        $value = trim((string)$value);

        if ($value == '') {
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
     * @param string $parameter
     * @param array $args
     *
     * @return null|string
     * @throws ValidatorConfigException
     * @example 'csrf_token' => 'anyOf:one,two,3,four'
     */
    protected function validateCsrf(string $parameter, array $args = []): ?string
    {
        $value = $this->getValue($parameter);

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

}
