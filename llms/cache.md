# Koldy PHP Framework — Cache

`Koldy\Cache` is a static facade for a multi-adapter caching system.

## Configuration

```php
// configs/cache.php
return [
    'default' => [
        'enabled' => true,
        'adapter_class' => \Koldy\Cache\Adapter\Files::class,
        'options' => [
            'path' => null  // null = auto (storage_path/cache/)
        ]
    ],
    'runtime' => [
        'enabled' => true,
        'adapter_class' => \Koldy\Cache\Adapter\Runtime::class,
        'options' => []
    ],
    'memcached' => [
        'enabled' => true,
        'adapter_class' => \Koldy\Cache\Adapter\Memcached::class,
        'options' => [
            'servers' => [
                ['host' => '127.0.0.1', 'port' => 11211, 'weight' => 100]
            ]
        ]
    ]
];
```

## Basic Operations

```php
use Koldy\Cache;

// Get and set
Cache::set('key', 'value');                // cache indefinitely
Cache::set('key', 'value', 3600);          // cache for 1 hour
$value = Cache::get('key');                 // returns null if not found

// Check and delete
Cache::has('key');        // bool
Cache::delete('key');

// Get or compute and cache
$value = Cache::getOrSet('expensive-key', function () {
    return computeExpensiveResult();
}, 3600);
```

## Multi-Key Operations

```php
// Get multiple values at once
$values = Cache::getMulti(['key1', 'key2', 'key3']);
// Returns: ['key1' => 'value1', 'key2' => 'value2', 'key3' => null]

// Set multiple values at once
Cache::setMulti([
    'key1' => 'value1',
    'key2' => 'value2'
], 3600);

// Delete multiple
Cache::deleteMulti(['key1', 'key2']);
```

## Increment/Decrement

```php
Cache::set('counter', 0);
Cache::increment('counter');        // 1
Cache::increment('counter', 5);    // 6
Cache::decrement('counter');        // 5
Cache::decrement('counter', 3);    // 2
```

## Using Specific Adapters

```php
// Use a named adapter instead of default
Cache::getAdapter('memcached')->set('key', 'value');

// Check adapter
Cache::hasAdapter('memcached');        // bool
Cache::isEnabled('memcached');         // bool
```

## Built-in Adapters

| Adapter | Class | Description |
|---------|-------|-------------|
| Runtime | `Cache\Adapter\Runtime` | In-memory PHP array. Data exists only for the current request. Fast, no I/O. |
| Files | `Cache\Adapter\Files` | File-based caching. Serialized data stored on disk. |
| Db | `Cache\Adapter\Db` | Database table caching. Stores cached data in a database table. |
| Memcached | `Cache\Adapter\Memcached` | Memcached server. Requires `ext-memcached`. |
| DevNull | `Cache\Adapter\DevNull` | Null adapter. All operations are no-ops. Useful for testing or disabling cache. |

## Getting Config

```php
$config = Cache::getConfig();  // Config instance
```
