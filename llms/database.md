# Koldy PHP Framework — Database & ORM

## Db Facade

`Koldy\Db` is the static facade for database operations. It manages adapter instances and provides query building shortcuts.

### Configuration

```php
// configs/database.php
return [
    'primary' => [
        'type' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'username' => 'root',
        'password' => '',
        'database' => 'myapp',
        'charset' => 'utf8mb4',
        'persistent' => false
    ],
    'secondary' => 'primary',  // pointer config — reuses 'primary' settings
];
```

Supported adapter types: `mysql`, `postgres`, `sqlite`.

### Adapter Management

```php
Db::getAdapter();              // get default adapter
Db::getAdapter('secondary');   // get named adapter
Db::hasAdapter('primary');     // bool
Db::addAdapter('custom', $adapter);
Db::removeAdapter('custom');   // close and remove
Db::removeAdapters();          // close all
```

### Transactions

```php
Db::beginTransaction();
try {
    // ... database operations ...
    Db::commit();
} catch (Throwable $e) {
    Db::rollBack();
    throw $e;
}
```

### Raw Queries

```php
$result = Db::query('SELECT * FROM users WHERE id = ?', [5]);
$result = Db::query('SELECT * FROM users WHERE id = :id', ['id' => 5]);
```

### Query Builder Shortcuts

```php
Db::select('users')->fetchAll();                       // new Select query on 'users' table
Db::insert('users', ['name' => 'John'])->exec();       // new Insert query
Db::update('users', ['name' => 'Jane'])->exec();       // new Update query
Db::delete('users')->where('id', 1)->exec();           // new Delete query
Db::expr('NOW()');                                     // raw SQL expression that will be used as is in query
```

---

## Model (ActiveRecord ORM)

`Koldy\Db\Model` is the base class for database models. Extend it to define models for your tables. It simplifies CRUD operations and provides query builder integration for database table queries.

### Defining a Model

```php
use Koldy\Db\Model;

class User extends Model
{
    protected static string|null $table = 'users';           // name of table in database
    protected static string|array $primaryKey = 'id';        // name of the primary key field; could be array of fields
    protected static string|bool $autoIncrement = true;      // tells if primary key is auto-incrementing field; in case of composite primary key, set to false
    protected static string|null $adapter = null;            // null by default means default adapter, but you can set it to specific adapter key from configs/database.php
}
```

If `$table` is null, it is auto-detected from the class name: `\App\Db\User` → `app_db_user`.

### CRUD Operations

```php
// Create
$user = new User(['name' => 'John', 'email' => 'john@example.com']);
$user->save();  // inserts if new, updates if existing; framework knows you didn't provide primary key, so it assumes record doesn't exist in database

// Or explicit insert
$user = User::create(['name' => 'John', 'email' => 'john@example.com']);
$id = $user->id;  // returns auto-increment ID on MySQL/PostgreSQL; on SQLite, it's not supported

// Read
$user = User::fetchOne(5);             // find by primary key, returns null if not found
$users = User::fetch(\Koldy\Db\Where::init()->where('id', '>=', 5));          // find all
$users = User::fetch(['active' => true]);  // find with conditions
$count = User::count(['active' => true]);

// Update
$user = User::fetchOne(5);
$user->set('name', 'Jane');
$user->save();
// Or:
User::update(['name' => 'Jane'], 5);

// Delete
$user->destroy();
// Or:
User::delete(5);

// Refresh from database
$user->reload();
```

### Data Access

```php
$user->get('name');           // get property
// or
$user->name;                  // get property
$user->set('name', 'Jane');  // set property
// or
$user->name = 'Jane';        // set property
$user->getData();             // all data as array
$user->getOriginalData();     // data as loaded from DB
$user->isDirty();             // has data changed since load (compares if original data is different from current data)
```

### Query Building on Models

```php
// Select builder scoped to model's table
$users = User::select('u')
    ->field('u.id')
    ->field('u.name')
    ->where('u.active', true)
    ->orderBy('u.name')
    ->fetchAll();

// Shorthand where
$users = User::fetch(\Koldy\Db\Where::init()->where('active', true)->where('role', 'admin'))->fetch();

// Batch insert
User::insert()->addRows([
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Bob', 'email' => 'bob@example.com'],
])->exec();
```

### Transactions on Models

```php
User::beginTransaction();
User::commit();
User::rollBack();
```

## Query Builder

### Select

```php
use Koldy\Db\Query\Select;

$select = new Select('users', 'u');
// or
$select = Db::select('users', 'u');

$select
    ->field('u.id')
    ->field('u.name')
    ->field('u.email')
    ->from('roles', 'r')                          // additional FROM table
    ->innerJoin('user_roles ur', 'ur.user_id', '=', 'u.id')
    ->leftJoin('profiles p', 'p.user_id', '=', 'u.id')
    ->where('u.active', true)
    ->where('u.created_at', '>=', '2024-01-01')
    ->whereIn('u.role', ['admin', 'editor'])
    ->whereNull('u.deleted_at')
    ->whereNotNull('u.email')
    ->groupBy('u.id')
    ->having('COUNT(*)', '>', 1)
    ->orderBy('u.name', 'ASC')
    ->limit(0, 25);

// Fetching results
$rows = $select->fetch();            // array of arrays
$row = $select->fetchFirst();           // first row as array
$obj = $select->fetchFirstObj();        // first row as stdClass
$obj = $select->fetchFirstObj(User::class); // first row as instance of User model
$allObj = $select->fetchAllObj();       // array of stdClass
$allObj = $select->fetchAllObj(User::class); // array of User model instances

// Generator for large result sets
foreach ($select->fetchAllGenerator() as $row) {
    // process one row at a time
}

```

### Insert

```php
use Koldy\Db\Query\Insert;

$insert = Db::insert('users', [
    'name' => 'John',
    'email' => 'john@example.com'
]);
$insert->exec();

// or insert multiple
$insert = Db::insert('users', [
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Bob', 'email' => 'bob@example.com'],
]);
$insert->exec();

// Or step by step
$insert = new Insert('users');
$insert->add('name', 'John');
$insert->add('email', 'john@example.com');
$insert->exec();
```

### Update

```php
use Koldy\Db\Query\Update;

$update = Db::update('users', ['name' => 'Jane'])->where('id', 5);
$update->exec();  // returns number of affected rows
```

### Delete

```php
use Koldy\Db\Query\Delete;

$delete = Db::delete('users')
    ->where('active', false);
$delete->exec();
```

### Where Conditions

The `Where` class (extended by Select, Update, Delete) provides:

```php
->where('field', $value)                     // field = value
->where('field', '>', $value)                // field > value
->where('field', 'LIKE', '%search%')         // LIKE
->whereIn('field', [1, 2, 3])               // IN (...)
->whereNotIn('field', [1, 2, 3])            // NOT IN (...)
->whereNull('field')                         // IS NULL
->whereNotNull('field')                      // IS NOT NULL
->whereBetween('field', $min, $max)          // BETWEEN
->whereRaw('YEAR(created_at) = ?', [2024])   // raw SQL condition
->orWhere('field', $value)                   // OR condition
```

For Postgres GIN operations, you can use:

```php
->where('field', '@>', \Koldy\Json::encode($value)) // field contains value

// Postgres GIN operator - check if key exists in JSON object
->where('field', '?', 'key_name') // WRONG OPERATOR
->where('field', '??', 'key_name') // CORRECT OPERATOR
```

Postgres GIN operators have "?" operator that checks the presence of value in array. This is in conflict with PDO's placeholder, so you have to
use "??" instead - it will be automatically converted to "?".

### Bindings & Prepared Statements

All query builder methods use PDO prepared statements with parameter binding for SQL injection prevention. You never need to manually escape values.

### ResultSet

`Koldy\Db\Query\ResultSet` wraps query results with convenience methods for iterating, counting, and accessing rows.

---

## Database Migration System

CLI commands for managing database schema:

```bash
# Create a new migration
php public/index.php koldy create-migration CreateUsersTable

# Run pending migrations
php public/index.php koldy migrate
// or
php public/index.php koldy migrate --force

# Rollback the last migration batch
php public/index.php koldy rollback
// or
php public/index.php koldy rollback 5
```

Migration classes extend `Koldy\Db\Migration\AbstractMigration` and implement `up()` and `down()` methods.
