# AGENTS.md

Instructions for AI coding agents working with this repository.

## Project Overview

Koldy PHP Framework (`koldy/framework`) is a zero-dependency near-core PHP framework for rapid web development. It's small and simple because it gives you all the basic functionality you need to build web apps. It's best used on medium and large scale enterprise projects where you want to have full control over your code, but still be near-core PHP without any bloat. Small framework also means smaller context usage for your AI tools.

- **Namespace**: `Koldy\` — all source code in `src/`, PSR-4 autoloaded
- **Minimum PHP**: 8.3
- **Dependencies**: Zero production dependencies. Only PHP extensions are suggested as optional (`ext-pdo`, `ext-mbstring`, `ext-memcached`, `ext-openssl`, `ext-iconv`, `ext-json`, `ext-curl`, `ext-ctype`, `ext-bcmath`)
- **Tests**: PHPUnit 12, in `tests/` under the `Tests\` namespace
- **Static analysis**: PHPStan

## Commands

```bash
# Run tests
./dev.sh test

# Run a single test class
./dev.sh test --filter UtilTest

# Run a single test method
./dev.sh test --filter UtilTest::testRandomString

# Run PHPStan static analysis (default level 5)
./dev.sh phpstan

# Run PHPStan at a specific level
./dev.sh phpstan 6

# Run with a specific PHP version
./dev.sh php 8.3 test
./dev.sh php 8.3 phpstan
```

Direct commands (without PHP version switching):
```bash
php bin/phpunit --configuration phpunit.xml.dist
php bin/phpstan analyse -c phpstan.neon --memory-limit 4G --level 5
```

## Code Conventions

- All PHP files use `declare(strict_types=1)`
- Full type hints on all method signatures and properties
- Union types preferred over nullable when multiple types are valid (e.g. `string|null` not `?string`)

## Architecture Patterns

### Static Facade Pattern

Most framework classes expose static methods as the primary API. Classes like `Application`, `Db`, `Cache`, `Log`, `Session`, `Request`, and `Mail` are accessed statically and manage internal state/adapters. When adding new functionality, follow this pattern.

```php
Cache::set('key', 'value', 3600);
Log::error('Something went wrong');
$ip = Request::ip();
```

### Adapter Pattern

Major subsystems use a pluggable adapter architecture configured via PHP config files:

| Subsystem | Abstract Base | Adapters |
|-----------|--------------|----------|
| Database (`Db`) | `Db\Adapter\AbstractAdapter` | MySQL, PostgreSQL, SQLite |
| Cache (`Cache`) | `Cache\Adapter\AbstractCacheAdapter` | Runtime, Files, Db, Memcached, DevNull |
| Log (`Log`) | `Log\Adapter\AbstractLogAdapter` | File, Db, Email, Out, Other |
| Session (`Session`) | `Session\Adapter\AbstractSessionAdapter` | File, Db |
| Mail (`Mail`) | `Mail\Adapter\AbstractMailAdapter` | Mail, File, Simulate |

When adding a new adapter, extend the abstract base class for that subsystem.

### Pointer Config Pattern

Named connections support string references to avoid duplicating configuration:

```php
// configs/database.php
return [
    'primary' => ['type' => 'mysql', 'host' => 'localhost', ...],
    'secondary' => 'primary',  // points to 'primary' config
];
```

## Key Classes

### Application (`src/Application.php`)

Central orchestrator. Initializes configs, resolves routes, dispatches to controllers, manages the response lifecycle. All methods are static.

- `Application::useConfig(array $config)` — initialize with config
- `Application::run()` — start request handling
- `Application::getConfig(string $name)` — get named config
- `Application::getApplicationPath()`, `getStoragePath()`, `getPublicPath()`, `getViewPath()` — path resolution
- `Application::inDevelopment()`, `inProduction()`, `inTest()` — environment checks
- `Application::afterAnyResponse(callable $fn)` — register post-response hooks (executes after response sent to client)

### Routing

Two systems, both extend `Route\AbstractRoute`:

**HttpRoute** (`Route/HttpRoute.php`) — recommended. Filesystem-based HTTP router using PSR-4 autoloading.
- URI segments map left-to-right to namespaced classes: `/companies/splendido-solutions/invoices` → `App\Http\Companies` → `App\Http\Companies\__` → `App\Http\Companies\__\Invoices`
- Segment sanitization: slugified → PascalCased (e.g. `bank-accounts` → `BankAccounts`)
- Dynamic matching: class named `__` (double underscore) is a wildcard catch-all, receives raw segment via `$this->segment`. Dynamic matches always take precedence over static matches.
- Context propagation: controllers enrich a `$context` array passed to the next controller in the chain
- Final class handles the request via HTTP method-named methods: `get()`, `post()`, `patch()`, `delete()`, etc.
- All controllers must extend `Route\HttpRoute\HttpController`
- `HttpController::once(string $name, callable $callback)` — execute code once per request cycle, useful for authentication in abstract controllers that might be inherited by multiple controllers in the chain

**DefaultRoute** (`Route/DefaultRoute.php`) — legacy. URL pattern: `/[module]/[controller]/[action]/[params...]`

### Request (`src/Request.php`)

Static facade wrapping `$_SERVER`, `$_GET`, `$_POST`, `$_FILES`.
- `Request::ip()` — real client IP (detects proxies)
- `Request::method()`, `isGet()`, `isPost()`, `isPut()`, `isDelete()`, `isPatch()`
- `Request::getAllParameters()` — universal parameter retrieval (handles JSON, multipart, form-data)
- `Request::requireParams('email', 'password')` — throws `BadRequestException` if missing

### Response (`src/Response/`)

All extend `AbstractResponse`. Types: `Json`, `View`, `Plain`, `Redirect`, `FileDownload`, `ContentDownload`.

HTTP exception classes under `Response\Exception\`: `BadRequestException` (400), `UnauthorizedException` (401), `ForbiddenException` (403), `NotFoundException` (404), `MethodNotAllowedException` (405), `ServerException` (500). Throw these from controllers and the exception handler renders the appropriate response.

### Database (`src/Db.php`, `src/Db/`)

- `Db` — static facade for database operations, adapter management, transactions
- `Db\Model` — ActiveRecord ORM base class. Extend it, set `$table`, `$primaryKey`, `$autoIncrement`, `$adapter`
  - CRUD: `User::create([...])`, `User::fetchOne(5)`, `User::fetch([...])`, `$user->save()`, `$user->destroy()`, `User::delete(5)`
  - Data: `$user->name` (magic get/set), `$user->getData()`, `$user->isDirty()`
- `Db\Query\Select`, `Insert`, `Update`, `Delete` — query builders with PDO prepared statements
- `Db\Where` — where clause builder, used by Select, Update, Delete
- Postgres GIN: use `??` instead of `?` operator (auto-converted, avoids PDO placeholder conflict)

### Validator (`src/Validator.php`)

Rule-based validation engine. Pipe-separated rules with colon parameters:

```php
$validator = Validator::create([
    'email' => 'required|email',
    'password' => 'required|minLength:8',
    'role' => 'required|anyOf:admin,user,editor'
]);
$data = $validator->getValid();
```

25+ built-in rules: `required`, `present`, `integer`, `numeric`, `decimal:N`, `boolean`, `alpha`, `alphaNum`, `hex`, `email`, `slug`, `uuid`, `date`, `array`, `min:N`, `max:N`, `minLength:N`, `maxLength:N`, `length:N`, `same:field`, `different:field`, `is:value`, `anyOf:v1,v2`, `startsWith:prefix`, `endsWith:suffix`, `unique:Table,column`, `exists:Table,column`, `csrf`. Closures supported for custom rules.

### Cache (`src/Cache.php`)

Static facade. Adapters: Runtime (in-memory), Files, Db, Memcached, DevNull.
- `Cache::get()`, `set()`, `has()`, `delete()`, `getOrSet()`, `getMulti()`, `setMulti()`, `increment()`, `decrement()`

### Log (`src/Log.php`)

Static facade. PSR-3 severity levels. Adapters: File, Db, Email, Out, Other.
- `Log::emergency()`, `alert()`, `critical()`, `error()`, `warning()`, `notice()`, `info()`, `debug()`, `sql()`
- `Log::setWho('user:42')` — identify who is generating log entries
- Custom adapters: extend `Log\Adapter\AbstractLogAdapter`, implement `logMessage` method

### Session (`src/Session.php`)

Static facade. Adapters: File, Db.
- `Session::start()`, `set()`, `get()`, `has()`, `delete()`, `getOrSet()`, `destroy()`

### Mail (`src/Mail.php`)

Static facade. Adapters: Mail (PHP `mail()`), File (for testing), Simulate (no-op). For SMTP, use companion package `koldy/phpmailer`. Custom adapters: extend `Mail\Adapter\AbstractMailAdapter`.

### Utilities

- **Config** (`src/Config.php`): loads PHP config files, supports pointer configs, accessed via `Application::getConfig()`
- **Crypt** (`src/Crypt.php`): OpenSSL encryption/decryption, uses application key by default
- **Json** (`src/Json.php`): encoding/decoding with exception handling
- **Url** (`src/Url.php`): URL parsing and component extraction
- **Cookie** (`src/Cookie.php`): cookie management, encrypted by default via `Crypt`
- **Util** (`src/Util.php`): `randomString()`, `truncate()`, `slug()`, `camelToSlug()`, `cleanString()`, HTML escaping helpers, `pick()` for arrays, `dateToDb()`/`dbToDate()`
- **Filesystem** (`src/Filesystem/`): `File` and `Directory` classes for file/directory operations
- **Server** (`src/Server.php`): server IP and hostname

### Security

- **CSRF** (`src/Security/Csrf/`): token generation and validation, not automatically enabled — developer decides how to distribute and validate tokens
- **SQL injection**: all query builders use PDO prepared statements
- **XSS**: `Util::attributeValue()`, `Util::quotes()`, `Util::tags()` for escaping
- **Cookies**: encrypted by default via `Crypt`

### Testing with Mock (`src/Mock.php`)

`Mock` simulates HTTP requests in PHPUnit tests. It backs up PHP superglobals (`$_GET`, `$_POST`, `$_SERVER`, `$_FILES`, `$_COOKIE`, `$_SESSION`), lets you override them to simulate any HTTP request, then restores originals on reset. Also resets `Request` class internal static state via reflection. Only works when `Application::inTest()` is true.

Pattern — use `Mock::start()` in `setUp()` and `Mock::reset()` in `tearDown()`:

```php
protected function setUp(): void { Mock::start(); }
protected function tearDown(): void { Mock::reset(); }
```

Methods:
- `Mock::request($method, $uri, $params, $headers, $rawData, $files)` — simulate a full HTTP request (sets `$_SERVER`, `$_GET`/`$_POST`, raw body, `$_FILES`)
- `Mock::json($method, $uri, $data, $headers)` — simulate a JSON request (auto sets `Content-Type: application/json`, `Accept: application/json`, JSON-encodes body)
- `Mock::get($params)` — set `$_GET`
- `Mock::post($params)` — set `$_POST`
- `Mock::server($params)` — merge into `$_SERVER`
- `Mock::files($files)` — set `$_FILES`
- `Mock::rawData($data)` — set raw request body

### Migrations

CLI-based database migration system. Raw SQL (no schema abstraction). No built-in transaction support in migrations.

```bash
php public/index.php koldy create-migration CreateUsersTable
php public/index.php koldy migrate
php public/index.php koldy rollback
```

Migration classes extend `Koldy\Db\Migration\AbstractMigration` with `up()` and `down()` methods. Class naming: `Migration_{timestamp}_{Name}`.

## Detailed Documentation

For comprehensive API documentation with full code examples, see:
- `llms.txt` — structured index of all documentation
- `llms/` — detailed documentation files for each subsystem
