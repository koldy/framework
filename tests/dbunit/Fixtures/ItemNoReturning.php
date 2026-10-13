<?php

declare(strict_types=1);

namespace Tests\DbUnit\Fixtures;

/**
 * Same table as Item, explicitly never using RETURNING (which is also the Model default); the primary key is read
 * through lastInsertId()
 */
class ItemNoReturning extends Item
{

	protected static bool|array $returning = false;

}
