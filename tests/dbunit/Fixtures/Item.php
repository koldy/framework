<?php

declare(strict_types=1);

namespace Tests\DbUnit\Fixtures;

use Koldy\Db\Model;

/**
 * Model over the koldy_dbunit_items table (created per test by InsertReturningTestCase). Uses the framework defaults:
 * primary key "id", auto increment, RETURNING * on insert.
 *
 * @property int|string $id
 * @property string $name
 * @property string $created_at
 * @property bool|int|string $active
 * @property mixed $payload
 */
class Item extends Model
{

	protected static string|null $table = 'koldy_dbunit_items';

}
