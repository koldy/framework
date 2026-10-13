<?php

declare(strict_types=1);

namespace Tests\DbUnit\Fixtures;

/**
 * Same table as Item, but opted in to RETURNING *: create() and inserting save() hold the full row as stored
 */
class ItemReturning extends Item
{

	protected static bool|array $returning = true;

}
