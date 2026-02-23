# Koldy PHP Framework — Validation

`Koldy\Validator` is a rule-based data validation engine. It validates arrays of data against string-based rules, similar to Laravel's validator.

## Basic Usage

```php
use Koldy\Validator;

// Validate request parameters automatically
$validator = Validator::create([
    'email' => 'required|email',
    'password' => 'required|minLength:8',
    'name' => 'required|alpha|maxLength:100'
]);
// Throws Validator\Exception if validation fails

// Access validated data
$data = $validator->getValid();          // array
$data = $validator->getValidObject();    // stdClass
$email = $validator->getValid('email');  // single field
```

## Manual Validation

```php
// Validate custom data (not from request)
$validator = new Validator([
    'age' => 'required|integer|min:0|max:150',
    'email' => 'required|email'
], [
    'age' => '25',
    'email' => 'user@example.com'
]);

if ($validator->validate()) {
    $data = $validator->getValid();
} else {
    $errors = $validator->getInvalid();  // field => error message
}
```

## Strict Mode

Only allow fields defined in rules — extra fields cause validation failure:

```php
$validator = new Validator([
    'email' => 'required|email',
    'password' => 'required'
]);
$validator->setOnly(true);  // reject any parameters not in rules
$validator->validate();
```

## Checking Results

```php
$validator->isAllValid();          // bool — all fields valid
$validator->isValid('email');      // bool — specific field valid
$validator->getValid();            // all valid data as array
$validator->getValid('email');     // single valid value
$validator->getInvalid();          // all invalid fields with messages
$validator->getInvalid('email');   // error message for specific field
```

## Built-in Validation Rules

### Presence Rules

| Rule | Description |
|------|-------------|
| `required` | Field must be present and non-empty |
| `present` | Field must exist in data (can be empty) |

### Type Rules

| Rule | Description |
|------|-------------|
| `integer` | Must be a valid integer |
| `numeric` | Must be a numeric value |
| `decimal:N` | Must be a decimal with at most N decimal places |
| `boolean` or `bool` | Must be a boolean-like value (true, false, 1, 0, "1", "0") |
| `alpha` | Must contain only alphabetic characters |
| `alphaNum` | Must contain only alphanumeric characters |
| `hex` | Must be a valid hexadecimal string |
| `email` | Must be a valid email address |
| `slug` | Must be a valid URL slug (lowercase, dashes, no spaces) |
| `uuid` | Must be a valid UUID (8-4-4-4-12 hex format) |
| `date` | Must be a valid date string |
| `array` or `array:N` | Must be an array, optionally with exactly N elements |

### Size Rules

| Rule | Description |
|------|-------------|
| `min:N` | Minimum numeric value |
| `max:N` | Maximum numeric value |
| `minLength:N` | Minimum string length |
| `maxLength:N` | Maximum string length |
| `length:N` | Exact string length |

### Comparison Rules

| Rule | Description |
|------|-------------|
| `same:field` | Must have the same value as another field |
| `different:field` | Must have a different value than another field |
| `is:value` | Must equal the exact value |
| `anyOf:val1,val2,...` | Must be one of the listed values |
| `startsWith:prefix` | Must start with the given prefix |
| `endsWith:suffix` | Must end with the given suffix |

### Database Rules

| Rule | Description |
|------|-------------|
| `unique:Table,column` | Must be unique in the database table |
| `exists:Table,column` | Must exist in the database table |

### Security Rules

| Rule | Description |
|------|-------------|
| `csrf` | Validates CSRF token |

## Rule Syntax

Rules are pipe-separated strings. Rules with parameters use colon notation:

```php
'field' => 'required|email'
'field' => 'required|minLength:3|maxLength:100'
'field' => 'required|anyOf:active,inactive,pending'
'field' => 'integer|min:0|max:999'
'field' => 'required|unique:App\\Db\\User,email'
'field' => 'required|decimal:2'
'field' => 'required|same:password_confirmation'
```

## Custom Validation with Closures

You can pass a closure as a rule for custom validation logic:

```php
$validator = new Validator([
    'email' => function (mixed $value, string $field, array $allData): ?string {
        // Return null if valid, or error message string if invalid
        if (!str_ends_with($value, '@company.com')) {
            return 'Email must be a company email address';
        }
        return null;
    }
]);
```

## Factory Method

The `create()` factory validates immediately and throws on failure:

```php
// This throws Validator\Exception if validation fails
$validator = Validator::create([
    'email' => 'required|email',
    'password' => 'required|minLength:8'
]);

// With custom data
$validator = Validator::create($rules, $customData);

// Without auto-throw
$validator = Validator::create($rules, $data, false);
if (!$validator->isAllValid()) {
    // handle errors
}
```
