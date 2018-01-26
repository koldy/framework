<?php declare(strict_types=1);

namespace Koldy\Response\Exception;

use Koldy\Response\Exception as ResponseException;

class UnauthorizedException extends ResponseException
{

    public function __construct($message = 'Unauthorized', $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

}