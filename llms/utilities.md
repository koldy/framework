# Koldy PHP Framework — Utilities

## Config

`Koldy\Config` loads and manages configuration from PHP files or arrays.

```php
use Koldy\Config;

// Usually accessed through Application
$config = Application::getConfig('database');

$config->get('primary');         // get value by key
$config->has('primary');         // bool
$config->set('key', 'value');    // set at runtime
$config->delete('key');          // remove key
$config->getData();              // full config array

// Nested access
$config->getArrayItem('primary', 'host');

// Validate required keys exist
$config->checkPresence(['host', 'port', 'database']);

// Check config freshness
$config->isOlderThan(60);       // older than 60 seconds
$config->reload();               // reload from disk
```

## Crypt

`Koldy\Crypt` provides OpenSSL-based encryption and decryption. Uses the application key by default.

```php
use Koldy\Crypt;

$encrypted = Crypt::encrypt('secret data');
$decrypted = Crypt::decrypt($encrypted);

// With custom key and method
$encrypted = Crypt::encrypt('data', 'custom-key', 'aes-256-cbc');
$decrypted = Crypt::decrypt($encrypted, 'custom-key', 'aes-256-cbc');
```

## Json

`Koldy\Json` provides JSON encoding/decoding with proper error handling (throws exceptions on failure).

```php
use Koldy\Json;

$json = Json::encode(['key' => 'value']);          // string
$array = Json::decode('{"key":"value"}');           // array
$obj = Json::decodeToObj('{"key":"value"}');         // stdClass

// With custom flags
$json = Json::encode($data, JSON_PRETTY_PRINT);
```

## Url

`Koldy\Url` parses and extracts URL components.

```php
use Koldy\Url;

$url = new Url('https://user:pass@example.com:8080/path?q=1#section');

$url->getScheme();     // "https"
$url->getHost();       // "example.com"
$url->getPort();       // 8080
$url->getUser();       // "user"
$url->getPassword();   // "pass"
$url->getPath();       // "/path"
$url->getQuery();      // "q=1"
$url->getFragment();   // "section"
$url->getFullUrl();    // full reconstructed URL
```

## Cookie

`Koldy\Cookie` provides static methods for cookie management.

```php
use Koldy\Cookie;

Cookie::set('name', 'value');
Cookie::set('name', 'value', 3600);          // expires in 1 hour
Cookie::set('name', 'value', 3600, '/');     // with path
$value = Cookie::get('name');                 // ?string
Cookie::has('name');                          // bool
Cookie::delete('name');
```

Cookies are encrypted by default using `Koldy\Crypt`, so you won't see their actual values in the browser's dev tools. Messing up with cookie values will cause MalformedException on the backend, basically invalidating cookie.

## Util

`Koldy\Util` provides static helper methods for strings, arrays, dates, and text processing.

### String Helpers

```php
use Koldy\Util;

Util::randomString(32);                   // cryptographic random string
Util::truncate('Long text...', 80);       // truncate with "..."
Util::cleanString("  too  many  spaces  "); // normalize whitespace
Util::slug('Hello World!');                // "hello-world"
Util::camelToSlug('myMethodName');         // "my-method-name"
Util::str2hex('text');                     // hex representation
```

### HTML Helpers

```php
Util::p("Line1\nLine2");                   // wrap in <p> tags
Util::a('Visit http://example.com');       // auto-linkify URLs
Util::attributeValue('He said "hi"');      // escape for HTML attributes
Util::quotes('He said "hi"');              // escape double quotes
Util::apos("It's");                        // escape single quotes
Util::tags('<script>alert(1)</script>');    // escape < and >
```

### Array Helpers

```php
Util::pick('key', $array);                            // get value or null
Util::pick('key', $array, 'default');                  // with default
Util::pick('status', $array, null, ['active', 'inactive']); // with allowed values
```

### Date Helpers

```php
Util::dateToDb(new DateTime());            // "2024-01-15 10:30:00"
Util::dbToDate('2024-01-15 10:30:00');     // DateTime instance
```

### Text Processing

```php
Util::parseMultipartContent($body, $contentType);  // parse multipart form data
```

## Convert

`Koldy\Convert` provides type conversion utilities.

```php
use Koldy\Convert;

// Numeric notation conversion (e.g. 1K, 2M, 3G)
use Koldy\Convert\NumericNotation;
```

## Filesystem

`Koldy\Filesystem\File` and `Koldy\Filesystem\Directory` provide file and directory operations.

```php
use Koldy\Filesystem\File;
use Koldy\Filesystem\Directory;

// File operations
File::read('/path/to/file.txt');
File::write('/path/to/file.txt', 'content');
File::append('/path/to/file.txt', 'more content');
File::exists('/path/to/file.txt');     // bool
File::delete('/path/to/file.txt');

// Directory operations
Directory::create('/path/to/dir');
Directory::exists('/path/to/dir');     // bool
Directory::delete('/path/to/dir');     // recursive delete
```

## Server

`Koldy\Server` provides server-related utility methods.

```php
use Koldy\Server;

Server::ip();           // server IP address
Server::name();         // server hostname
```

## Validate Helper

`Koldy\Validator\Validate` provides standalone validation checks (separate from the Validator engine):

```php
use Koldy\Validator\Validate;

Validate::isEmail('user@example.com');   // bool
Validate::isIP('192.168.1.1');           // bool
Validate::isSlug('valid-slug-123');      // bool
Validate::isUUID('550e8400-e29b-41d4-a716-446655440000'); // bool
```
