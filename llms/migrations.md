# Koldy PHP Framework — Database Migrations

Koldy includes a built-in database migration system managed via CLI commands.

## CLI Commands

```bash
# Create a new migration file
php public/index.php koldy create-migration CreateUsersTable

# Run all pending migrations
php public/index.php koldy migrate
// or
php public/index.php koldy migrate --force

# Rollback the last migration batch
php public/index.php koldy rollback
// or
php public/index.php koldy rollback 5
```

## Migration Structure

Migration classes extend `Koldy\Db\Migration\AbstractMigration` and implement `up()` (apply) and `down()` (rollback) methods.

```php
use Koldy\Db;
use Koldy\Db\Migration\AbstractMigration;

class Migration_1743627523_CreateUsersTable extends AbstractMigration
{
    public function up(): void
    {
        Db::query('
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL UNIQUE,
                name VARCHAR(100) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ')->exec();
    }

    public function down(): void
    {
        Db::query('DROP TABLE IF EXISTS users')->exec();
    }
}
```

Koldy provides simple ORM capabilities. Due to huge variations between database engines, we don't provide any abstraction for migrations, so you have to write raw SQL statements in migrations which is at the end much safer anyway.

Migrations do not provide transactions. If you need transactions, you have to implement them yourself within the migration.


## Migration Files

Migration files are stored in the application's migration directory and are timestamped for ordering. Each migration runs once and is tracked in a database table to prevent re-execution.
