# Koldy PHP Framework — Logging

`Koldy\Log` is a static facade for a multi-adapter logging system with severity levels following PSR-3 conventions.

## Configuration

```php
// configs/log.php
return [
    [
        'enabled' => true,
        'adapter_class' => \Koldy\Log\Adapter\File::class,
        'options' => [
            'path' => null,  // null = auto (storage_path/log/)
            'log' => ['error', 'warning', 'notice', 'info', 'debug', 'sql'],
            'mode' => 0755,
            'file_mode' => 0644
            'file_name_fn' => function () {
                return gmdate('Y-m-d') . '.log';
            },
            'dump' => ['speed', 'memory', 'whitespace'] // shows speed, memory usage and whitespace in log at the end of request
        ]
    ],
    [
        'enabled' => true,
        'adapter_class' => \Koldy\Log\Adapter\Email::class,
        'options' => [
            'send_to' => ['admin@example.com'],
            'log' => ['emergency', 'alert', 'critical'],
            'send_immediately' => true // send email immediately, don't wait for request to complete (will slow down the request)
        ]
    ],
    [
        'enabled' => true,
        'adapter_class' => \Koldy\Log\Adapter\Db::class,
        'options' => [
            'log' => ['error', 'warning', 'notice', 'info', 'debug', 'sql']
        ]
    ],
    [
        'enabled' => true,
        'adapter_class' => \Koldy\Log\Adapter\Out::class, // for CLI scripts
        'options' => [
            'log' => ['debug', 'sql']
        ]
    ]
];
```

To redirect logs to some external service, simply create your own adapter class that extends `Log\Adapter\AbstractLogAdapter` and implement the `logMessage` method, then add it to the configuration array.

## Logging by Severity

```php
use Koldy\Log;

Log::emergency('System is unusable');
Log::alert('Action must be taken immediately');
Log::critical('Critical conditions');
Log::error('Error conditions');
Log::warning('Warning conditions');
Log::notice('Normal but significant conditions');
Log::info('Informational messages');
Log::debug('Debug-level messages');
Log::sql('SELECT * FROM users WHERE id = 5'); // if you enable SQL logging, it will be automatically logged by framework's Db classes
```

All methods accept variadic arguments — multiple values are concatenated:

```php
Log::error('Failed to load user', $userId, $exception->getMessage());
```

## Log a Prepared Message

```php
use Koldy\Log\Message;

$message = new Message();
$message->setLevel('error');
$message->setMessage('Something went wrong');
Log::message($message);
```

## Who Identifier

Track who (which user/IP) is generating log entries:

```php
Log::setWho('user:42');
Log::getWho();      // "user:42"
Log::resetWho();    // reset to IP-based
```

This is useful when you have session and you detected the user so you can put anything you want in there (could be account ID, username, etc.)

## Request Identifier

Each request gets a unique random number for correlating log entries:

```php
Log::getRandomNumber();  // int — unique per request
```

## Temporary Disable/Enable

Advanced usage only:

```php
// Disable all logging temporarily
Log::temporaryDisable();

// Disable specific levels
Log::temporaryDisable(['debug', 'sql']);

// Check if disabled
Log::isTemporaryDisabled();        // bool
Log::isTemporaryDisabled('debug'); // bool

// Re-enable
Log::restoreTemporaryDisablement();
```

## Status Checks

```php
Log::isEnabled();                              // any adapter enabled
Log::isEnabledLevel('debug');                  // specific level enabled
Log::isEnabledLogger(Log\Adapter\File::class); // specific adapter enabled
```

## Built-in Adapters

| Adapter | Class | Description |
|---------|-------|-------------|
| File | `Log\Adapter\File` | File-based logging. One file per day by default. |
| Db | `Log\Adapter\Db` | Database table logging. |
| Email | `Log\Adapter\Email` | Send log entries via email. Good for critical alerts. |
| Out | `Log\Adapter\Out` | Standard output (stdout). Useful for CLI scripts. |
| Other | `Log\Adapter\Other` | Custom callback handler for external logging services. |
