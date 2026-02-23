# Koldy PHP Framework — Testing

`Koldy\Mock` is a helper class for simulating HTTP requests in PHPUnit tests. It backs up PHP superglobals (`$_GET`, `$_POST`, `$_SERVER`, `$_FILES`, `$_COOKIE`, `$_SESSION`), lets you override them to simulate any HTTP request, then restores the originals when you're done. It also resets internal static state in the `Request` class (raw data, parsed vars, uploaded files).

Mock can only be used when `Application::inTest()` returns true — it throws `SecurityException` otherwise.

## Basic Pattern

Use `Mock::start()` in `setUp()` and `Mock::reset()` in `tearDown()`:

```php
use Koldy\Mock;
use PHPUnit\Framework\TestCase;

class MyControllerTest extends TestCase
{
    protected function setUp(): void
    {
        Mock::start();  // backs up superglobals
    }

    protected function tearDown(): void
    {
        Mock::reset();  // restores original superglobals
    }

    public function testGetRequest(): void
    {
        Mock::request('GET', '/users?page=2');
        // $_SERVER['REQUEST_METHOD'] is now 'GET'
        // $_GET['page'] is now '2'
        // Request::isGet() returns true
    }
}
```

## Simulating Requests

### Generic HTTP Request

```php
Mock::request(
    string $method,    // HTTP method: GET, POST, PUT, DELETE, PATCH, etc.
    string $uri,       // Request URI, may include query string
    array $params,     // Request parameters
    array $headers,    // HTTP headers as key-value pairs
    string $rawData,   // Raw request body
    array $files       // Uploaded files ($_FILES format)
);
```

Examples:

```php
// Simple GET
Mock::request('GET', '/users');

// GET with query params
Mock::request('GET', '/users?page=2&limit=10');

// POST with form data
Mock::request('POST', '/login', ['email' => 'user@example.com', 'password' => 'secret']);

// PUT with custom headers
Mock::request('PUT', '/users/5', ['name' => 'Jane'], ['Authorization' => 'Bearer token123']);

// DELETE
Mock::request('DELETE', '/users/5');
```

### JSON Request

Shorthand for JSON `Content-Type` with auto-encoded body:

```php
Mock::json(
    string $method,    // HTTP method
    string $uri,       // Request URI
    array $data,       // Data to be JSON-encoded
    array $headers     // Additional headers (Content-Type and Accept are set automatically)
);
```

Example:

```php
Mock::json('POST', '/api/users', [
    'name' => 'John',
    'email' => 'john@example.com'
]);
// Sets Content-Type: application/json
// Sets Accept: application/json
// JSON-encodes the data as raw body
```

### Low-Level Mocking

For fine-grained control, set individual superglobals:

```php
Mock::start();

Mock::get(['page' => '1', 'sort' => 'name']);     // sets $_GET
Mock::post(['email' => 'user@example.com']);        // sets $_POST
Mock::server(['REQUEST_METHOD' => 'POST']);          // merges into $_SERVER
Mock::files([...]);                                  // sets $_FILES
Mock::rawData('{"custom":"body"}');                  // sets raw request body (via Request class reflection)
```

## Full Test Example

```php
use Koldy\Application;
use Koldy\Mock;
use Koldy\Route\HttpRoute;
use PHPUnit\Framework\TestCase;

class HttpRouteTest extends TestCase
{
    private static bool $appInitialized = false;

    public static function setUpBeforeClass(): void
    {
        if (!self::$appInitialized) {
            $_SERVER['SCRIPT_FILENAME'] = __FILE__;

            Application::useConfig([
                'site_url' => 'http://localhost',
                'env' => Application::TEST,
                'key' => 'TestKey12345',
                'timezone' => 'UTC',
                'paths' => [
                    'application' => __DIR__ . '/',
                    'storage' => __DIR__ . '/',
                ],
            ]);

            self::$appInitialized = true;
        }
    }

    protected function setUp(): void
    {
        Mock::start();
    }

    protected function tearDown(): void
    {
        Mock::reset();
    }

    public function testGetUsersEndpoint(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $route = new HttpRoute(['namespace' => 'App\\Http\\']);
        $response = $route->start('/users');

        $this->assertNotNull($response);
    }
}
```

## How It Works Internally

1. `Mock::start()` — saves current `$_GET`, `$_POST`, `$_SERVER`, `$_FILES`, `$_COOKIE`, `$_SESSION` into a backup array
2. `Mock::request()` / `Mock::json()` — calls `reset()` + `start()` to ensure clean state, then sets up `$_SERVER` (method, URI, headers), `$_GET` (query params), `$_POST` (for POST), raw body data (for PUT/DELETE/JSON), and `$_FILES`
3. `Mock::reset()` — restores all superglobals from backup and resets `Request` class static properties (`realIp`, `rawData`, `vars`, `uploadedFiles`, `parsedMultipartContent`) via reflection
