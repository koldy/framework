<?php

declare(strict_types=1);

namespace Tests\DbUnit\Fixtures;

/**
 * Same table as Item, but asks the database to return only the primary key on insert
 */
class ItemIdOnly extends Item
{

	protected static bool|array $returning = ['id'];

}
