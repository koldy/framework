<?php

declare(strict_types=1);

namespace Tests\DbUnit\Fixtures;

use Koldy\Db\Model;

/**
 * Model over the koldy_dbunit_categories table (created per test by Schema). Framework defaults: primary key "id",
 * auto increment, RETURNING * on insert.
 *
 * @property int|string $id
 * @property string $name
 */
class Category extends Model
{

	protected static string|null $table = 'koldy_dbunit_categories';

}
