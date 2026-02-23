# Koldy PHP Framework — Security

## CSRF Protection

`Koldy\Security\Csrf` provides Cross-Site Request Forgery protection with token generation and validation.

### Configuration

CSRF protection is configured in the application config, but it's not automatically enabled. The \Koldy\Security\Csrf class can help you generate and validate tokens, but it's up to developer to decide how to use it (via headers or request parameters) and how and when to distribute the tokens. After that, validation is simple.

### Usage

```php
use Koldy\Security\Csrf;

// Get the CSRF parameter name (for form fields)
Csrf::getParameterName();    // string, e.g. "_csrf"

// Generate a token
$token = Csrf::generateToken();

// Validate a token
Csrf::isTokenValid($token);  // bool
```

## General Security Practices

The framework follows security best practices:

- **SQL Injection Prevention**: All query builder methods use PDO prepared statements with parameter binding
- **XSS Prevention**: `Util::attributeValue()`, `Util::quotes()`, `Util::tags()` for escaping output
- **Cookie Security**: Session cookies support `httponly`, `secure`, and `path` options
- **Encryption**: `Crypt` class provides AES encryption via OpenSSL
- **Input Validation**: `Validator` class for comprehensive server-side validation
