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
