# Koldy PHP Framework — Session

`Koldy\Session` is a static facade for PHP session management with pluggable storage adapters.

## Configuration

```php
// configs/session.php
return [
    'adapter_class' => \Koldy\Session\Adapter\File::class,
    'options' => [
        'session_name' => 'your_app_name',
        'cookie_life' => 0,         // 0 = until browser closes
        'cookie_path' => '/',
        'cookie_domain' => null,
        'cookie_secure' => false,
        'cookie_http_only' => true
    ]
];
```

## Transport

The `transport` key in `configs/session.php` controls how the session ID is
delivered to the client on every response and read back on every request.
Two modes are available: `cookie` (default) and `header`.

### Cookie transport (default)

The session ID is sent and received as a standard HTTP cookie. This is the
appropriate choice for browser-based applications.

```php
// configs/session.php
return [
    'adapter_class' => \Koldy\Session\Adapter\File::class,
    'session_name'  => 'koldy',   // name of the session cookie

    'transport' => [
        'type' => 'cookie',       // default; can be omitted entirely
    ],

    // Cookie attributes — only used when transport.type is 'cookie'
    'cookie_life'     => 0,       // 0 = expire when browser closes
    'cookie_path'     => '/',
    'cookie_domain'   => '',
    'cookie_secure'   => true,    // send cookie over HTTPS only
    'http_only'       => true,    // inaccessible to JavaScript
    'cookie_samesite' => 'Lax',   // 'Lax', 'Strict', or 'None'
];
```

### Header transport

The session ID is sent in a custom HTTP **response** header and read from the
same header on incoming **requests**. This is the right choice for REST APIs,
mobile clients, and any consumer that cannot store cookies.

```php
// configs/session.php
return [
    'adapter_class' => \Koldy\Session\Adapter\File::class,
    'session_name'  => 'koldy',

    'transport' => [
        'type'        => 'header',
        'header_name' => 'X-Session',  // default: 'X-SESSION'
    ],
    // cookie_* keys are ignored when type is 'header'
];
```

On each response the framework emits the header automatically:

```
X-Session: <session-id>
```

The client must include that value on every subsequent request:

```
X-Session: <session-id>
```

A session can also be started with an explicit ID (e.g. passed via a
non-standard channel) by calling `Session::start()` with the ID directly,
which bypasses the header lookup:

```php
Session::start($request->getHeader('X-Session'));
```

### Transport config key reference

| Key | Applies to | Default | Description |
|-----|-----------|---------|-------------|
| `transport.type` | both | `'cookie'` | `'cookie'` or `'header'` |
| `transport.header_name` | `header` only | `'X-SESSION'` | Response/request header name |
| `cookie_life` | `cookie` only | `0` | Cookie lifetime in seconds; `0` = session cookie |
| `cookie_path` | `cookie` only | `'/'` | Cookie path scope |
| `cookie_domain` | `cookie` only | `''` | Cookie domain scope |
| `cookie_secure` | `cookie` only | `false` | HTTPS-only flag |
| `http_only` | `cookie` only | `false` | Hide cookie from JavaScript |
| `cookie_samesite` | `cookie` only | *(none)* | `'Lax'`, `'Strict'`, or `'None'` |

---

## Session Lifecycle

```php
use Koldy\Session;

Session::start();                     // initialize session
Session::start('custom-session-id');  // start with specific ID
Session::hasStarted();                // bool
Session::id();                        // current session ID

Session::close();      // flush data (no more writes allowed)
Session::isClosed();   // bool
Session::destroy();    // completely destroy session
```

## Data Management

```php
// Set and get
Session::set('user_id', 42);
$userId = Session::get('user_id');     // 42

// Check existence
Session::has('user_id');               // bool

// Set only if not already exists
Session::add('user_id', 42);          // only sets if key doesn't exist

// Delete
Session::delete('user_id');

// Get or compute and store
$cart = Session::getOrSet('cart', function () {
    return [];
});
```

## Built-in Adapters

| Adapter | Class | Description |
|---------|-------|-------------|
| File | `Session\Adapter\File` | File-based session storage (PHP default) |
| Db | `Session\Adapter\Db` | Database table session storage |

## Getting Config

```php
$config = Session::getConfig();
```
