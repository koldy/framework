<?php declare(strict_types=1);

namespace Koldy\Response\Exception;

use Koldy\Response\Exception as ResponseException;

class UnauthorizedException extends ResponseException
{

    public function __construct(string $message = 'Unauthorized', int $code = 0, \Exception|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

}
