<?php declare(strict_types=1);

namespace Koldy\Response\Exception;

use Koldy\Response\Exception as ResponseException;

class BadRequestException extends ResponseException
{

    public function __construct(string $message = 'Bad request', int $code = 0, \Exception|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

}
