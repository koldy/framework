# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Koldy is a dependency-free PHP 8.1+ MVC framework (`koldy/framework`). All source code lives under the `Koldy\` namespace in `src/`, with PSR-4 autoloading. The framework has zero production dependencies — only PHP extensions are suggested as optional.

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
./dev.sh php 8.1 test
./dev.sh php 8.1 phpstan
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

`Route/AbstractRoute` defines the routing contract. `Route/DefaultRoute` implements URL-to-controller mapping:
```
/[module]/[controller]/[action]/[param1]/[param2]/...
```

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

## Code Conventions

- All PHP files use `declare(strict_types=1)`
- Full type hints on all method signatures and properties
- Tests are in `tests/` under the `Tests\` namespace, using PHPUnit 12
