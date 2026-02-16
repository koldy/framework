<?php declare(strict_types=1);

namespace Koldy\Response\Exception;

use Exception;
use Koldy\Response\Exception as ResponseException;

class MethodNotAllowedException extends ResponseException
{

	public function __construct(string $message = 'Method not allowed', int $code = 0, Exception|null $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}

}
