# Koldy PHP Framework — Application

`Koldy\Application` is the central orchestrator of the framework. It initializes configs, resolves routes, dispatches to controllers, and manages the response lifecycle. All methods are static.

## Initialization

```php
// public/index.php

use Koldy\Application;

// Initialize with config array or path to config file
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

// Start handling the request
Application::run();

// Or initialize without running (useful for testing)
Application::init();
```

## Configuration Management

```php
// Get a named configuration object
$dbConfig = Application::getConfig('database');

// Pointer config (resolves named connections)
$dbConfig = Application::getConfig('database', true);

// Get the main application config
$appConfig = Application::getApplicationConfig();

// Set a config at runtime
Application::setConfig($configInstance);

// Check/remove configs
Application::hasConfig('database');  // bool
Application::removeConfig('database');

// Reload all configs from disk
Application::reloadConfig();
```

With this approach, you can create your own configuration files and load them as needed.

## Path Management

```php
Application::getApplicationPath();                    // /path/to/application/
Application::getApplicationPath('configs/app.php');   // /path/to/application/configs/app.php
Application::getStoragePath();                        // /path/to/storage/
Application::getStoragePath('logs/app.log');          // /path/to/storage/logs/app.log
Application::getPublicPath();                         // /path/to/public/
Application::getViewPath();                           // /path/to/application/views/
```

## Environment Detection

```php
Application::inDevelopment();   // bool
Application::inProduction();    // bool
Application::inTest();          // bool
Application::isLive();          // bool — is running live (not CLI)
Application::isCli();           // bool — is CLI execution
Application::isSSL();           // bool — is HTTPS
```

## Domain & URL

```php
Application::getDomain();              // "example.com"
Application::getDomainWithSchema();    // "https://example.com"
Application::getCurrentURL();          // Url instance of current request
Application::getEncoding();            // "UTF-8"
Application::name();                   // application name or null, just taken from application config "app_name" key
```

## Application Key

```php
// Get the secret key (used for encryption, etc.)
Application::getKey();
```

## Execution Time

```php
// Get request execution time in milliseconds
$ms = Application::getRequestExecutionTime();
```

## Post-Response Hooks

Register callbacks that execute after the response has been sent to the client:

```php
Application::afterAnyResponse(function () {
    // Send analytics, cleanup temp files, etc.
}, 'analytics-hook');

Application::isAfterAnyResponseFunctionRegistered('analytics-hook'); // bool
Application::removeAfterAnyResponseFunction('analytics-hook');
```

## Include Path

Discouraged, but if you must:

```php
Application::prependIncludePath('/path/to/extra/classes');
```
