<?php declare(strict_types=1);

namespace Koldy\Db\Exception;

/**
 * Class RecordNotFoundException
 * @package Koldy\Db\Exception
 *
 * Db methods will return null if record wasn't found. If you want to throw new Exception, you may use this one.
 */
class NotFoundException extends \Koldy\Db\Exception
{

}