# Koldy PHP Framework — Overview

> Koldy PHP Framework is zero-dependency near-core PHP framework for rapid web development. It's small and simple because it gives you all the basic functionality you need to build your web apps. It's best used on medium and large scale enterprise projects where you want to have full control over your code, but still be near-core PHP without any bloat. Small framework also means smaller context usage for your AI tools. All source code lives under the `Koldy\` namespace in `src/`, with PSR-4 autoloading.

## Installation

```bash
composer require koldy/framework
```

Minimum requirement: PHP 8.3. No production dependencies — only PHP extensions are suggested as optional:

- `ext-pdo` — Database operations - required for database operations
- `ext-mbstring` — Multibyte string handling - required for string operations
- `ext-memcached` — Memcached caching - required for memcached caching
- `ext-openssl` — Encryption/SSL - required for encryption/SSL operations and some random string generation
- `ext-iconv` — Character encoding conversion - required for character encoding conversion
- `ext-json`
- `ext-curl`
- `ext-ctype`
- `ext-bcmath`

## Architecture

### Static Facade Pattern

Most framework classes expose static methods as the primary API. Classes like `Application`, `Db`, `Cache`, `Log`, `Session`, `Request`, and `Mail` are accessed statically and manage internal state/adapters.

```php
// Examples of the static facade pattern
$user = Db::select('users')->where('id', 5)->fetchFirst();
Cache::set('key', 'value', 3600);
Log::error('Something went wrong');
Session::set('user_id', 42);
$ip = Request::ip();
```

### Adapter Pattern

Major subsystems use a pluggable adapter architecture configured via PHP config files:

| Subsystem | Adapters |
|-----------|----------|
| Database  | MySQL, PostgreSQL, SQLite |
| Cache     | Runtime, Files, Db, Memcached, DevNull |
| Log       | File, Db, Email, Out, Other |
| Session   | File, Db |
| Mail      | Mail (PHP mail()), File, Simulate |

### Pointer Config Pattern

Named connections support string references to avoid duplicating configuration:

```php
// configs/database.php
return [
    'primary' => ['type' => 'mysql', 'host' => 'localhost', ...],
    'secondary' => 'primary',  // points to 'primary' config
];
```

## Application Bootstrap

The `Application` class is the central orchestrator. A typical bootstrap:

```php
use Koldy\Application;

Application::useConfig([
    'application_path' => __DIR__ . '/application',
    'storage_path' => __DIR__ . '/storage',
    'public_path' => __DIR__ . '/public',
    'key' => 'your-secret-key',
    'timezone' => 'UTC',
    'routing_class' => '\Koldy\Route\HttpRoute',
    'routing_options' => [
        'namespace' => 'App\\Http\\'
    ]
]);

Application::run();
```

### Environment Modes

Three modes are available, checked via static methods:

- `Application::inDevelopment()` — Development mode
- `Application::inProduction()` — Production mode
- `Application::inTest()` — Test mode

### Project Structure

A typical Koldy project:

```
project/
├── application/
│   ├── configs/          # PHP config files
│   │   ├── application.php
│   │   ├── database.php
│   │   ├── cache.php
│   │   ├── log.php
│   │   ├── mail.php
│   │   ├── session.php
│   │   └── sites.php
│   ├── controllers/      # Controller classes (DefaultRoute)
│   ├── library/          # Application-specific classes
│   ├── views/            # View templates
│   └── scripts/          # CLI scripts
├── public/               # Web root (index.php entry point)
├── storage/              # Logs, cache, temp files
├── vendor/               # Composer dependencies
└── composer.json
```

When using `HttpRoute`, controllers live in a PSR-4 autoloaded namespace (e.g. `App\Http\`) instead of the `controllers/` directory.

### Hook System

Register callbacks to execute after any response is sent:

```php
Application::afterAnyResponse(function () {
    // cleanup, analytics, etc.
}, 'my-hook');
```

This will output any response to the client, client will close the connection, while server will continue to execute the code that was registered with `afterAnyResponse` method. This is very useful for analytics and other things that you want to do after the response is sent to the client, but you don't want to block the response.
