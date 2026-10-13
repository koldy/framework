<?php

declare(strict_types=1);

namespace Tests\DbUnit\Fixtures;

use Koldy\Db\Model;

/**
 * Model over the koldy_dbunit_memberships table (created per test by Schema). It has a composite primary key
 * (user_id, group_id) and no auto increment, so it exercises every composite-key code path in Model.
 *
 * @property int|string $user_id
 * @property int|string $group_id
 * @property string $role
 */
class Membership extends Model
{

	protected static string|null $table = 'koldy_dbunit_memberships';

	protected static string|array $primaryKey = ['user_id', 'group_id'];

	protected static string|bool $autoIncrement = false;

}
