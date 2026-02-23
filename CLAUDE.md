# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Koldy is a dependency-free PHP 8.3+ MVC framework (`koldy/framework`). All source code lives under the `Koldy\` namespace in `src/`, with PSR-4 autoloading. The framework has zero production dependencies — only PHP extensions are suggested as optional.

## Common Commands

All commands go through `dev.sh`, which manages PHP version switching via Homebrew (default PHP 8.3):

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

## Architecture

### Static Facade Pattern

Most framework classes expose static methods as the primary API. Classes like `Application`, `Db`, `Cache`, `Log`, `Session`, `Request`, and `Mail` are accessed statically and manage internal state/adapters.

### Adapter Pattern

Major subsystems use a pluggable adapter architecture:
- **Database** (`Db`): MySQL, PostgreSQL, SQLite adapters in `Db/Adapter/`
- **Cache** (`Cache`): Runtime, Files, Db, Memcached, DevNull adapters in `Cache/Adapter/`
- **Log** (`Log`): File, Db, Email, Out, Other adapters in `Log/Adapter/`
- **Session** (`Session`): File, Db adapters in `Session/Adapter/`

Adapters are loaded based on configuration. Each subsystem has an abstract adapter base class that concrete adapters extend.

### Application Bootstrap

`Application` (`src/Application.php`) is the central orchestrator — it initializes configs, resolves routes, dispatches to controllers, and manages the response lifecycle. Key concepts:
- **Environment modes**: DEVELOPMENT, PRODUCTION, TEST
- **Module system**: Optional application modules with their own controllers/views
- **Hook system**: `afterAnyResponse()` callbacks for post-response processing
- **Path management**: Tracks application, module, storage, view, and public paths

### Routing

`Route/AbstractRoute` defines the routing contract. Two router implementations are available:

**DefaultRoute** (`Route/DefaultRoute`) — legacy URL-to-controller mapping:
```
/[module]/[controller]/[action]/[param1]/[param2]/...
```

**HttpRoute** (`Route/HttpRoute`) — filesystem-based HTTP router using PSR-4 autoloading. URI segments map to namespaced classes under a configured root namespace, and the final class handles the request via HTTP method-named methods (`get()`, `post()`, `patch()`, `delete()`, etc.).

Key concepts:
- **Segment-to-class resolution**: each URI segment is slugified then PascalCased (e.g. `bank-accounts` → `BankAccounts`) and resolved to a class in the namespace tree
- **Dynamic matching (`__`)**: a class named `__` (double underscore) acts as a wildcard catch-all for a segment, receiving the raw segment value via `$this->segment`. Dynamic matches take precedence over static matches
- **Static matching**: if no `__` class exists, the router looks for a class whose PascalCased name matches the segment
- **Context propagation**: controllers in the chain receive and enrich a `$context` array, passing accumulated state to the next controller without global variables
- **Exception handling**: a custom `ExceptionHandler` class in the root namespace (with an `exec()` method) is used if present; otherwise the default `ResponseExceptionHandler` applies
- **Trailing slash**: GET/HEAD requests with a trailing slash are 301-redirected to the canonical URL without it

Configuration (`routing_options`):
```php
'routing_options' => [
  'namespace' => 'App\\Http\\',   // required — root namespace for handler classes
  'debugFailure' => true,          // optional — log why route resolution fails
  'debugSuccess' => true           // optional — log successful route resolution steps
]
```

**HttpController** (`Route/HttpRoute/HttpController`) — abstract base class that all HttpRoute controllers must extend. Provides:
- `$context` (array) — accumulated context from parent controllers
- `$segment` (string|null) — the raw URI segment that matched this controller
- `once(string $name, callable $callback)` — execute a callback only once per request cycle, useful when abstract controllers in the inheritance chain might otherwise run shared logic multiple times

### Request/Response Cycle

- `Request` (`src/Request.php`) handles incoming HTTP data (params, headers, files, IP detection)
- Responses extend `Response/AbstractResponse`: Json, View, Plain, Redirect, FileDownload, ContentDownload
- Response classes use a `Data` trait for managing response payload

### Database & ORM

- `Db` manages adapter instances and supports transactions
- `Db/Model` provides ActiveRecord-style ORM with auto-table naming, primary key handling, and CRUD
- `Db/Query/` contains a query builder: Select, Insert, Update, Delete, Where, Bindings

### Configuration

`Config` loads settings from files and supports pointer configs (named connections for database, mail, etc.). Configs are accessed via `Application::getConfig()`.

### Testing with Mock

`Mock` (`src/Mock.php`) simulates HTTP requests in PHPUnit tests. It backs up PHP superglobals (`$_GET`, `$_POST`, `$_SERVER`, `$_FILES`, `$_COOKIE`, `$_SESSION`), lets you override them, then restores originals on reset. It also resets `Request` class internal static state via reflection. Only works when `Application::inTest()` is true.

Usage pattern:
```php
protected function setUp(): void { Mock::start(); }
protected function tearDown(): void { Mock::reset(); }
```

Key methods:
- `Mock::request($method, $uri, $params, $headers, $rawData, $files)` — simulate a full HTTP request
- `Mock::json($method, $uri, $data, $headers)` — simulate a JSON request (auto sets Content-Type/Accept, JSON-encodes body)
- `Mock::get($params)`, `Mock::post($params)`, `Mock::server($params)`, `Mock::files($files)`, `Mock::rawData($data)` — set individual superglobals

## Code Conventions

- All PHP files use `declare(strict_types=1)`
- Full type hints on all method signatures and properties
- Tests are in `tests/` under the `Tests\` namespace, using PHPUnit 12
