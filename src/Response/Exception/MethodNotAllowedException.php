<?php declare(strict_types=1);

namespace Koldy\Response\Exception;

use Exception;
use Koldy\Response\Exception as ResponseException;

class MethodNotAllowedException extends ResponseException
{

	/**
	 * HTTP method names actually implemented on the controller, uppercased.
	 *
	 * @var array<int, string>
	 */
	protected array $allowedMethods = [];

	public function __construct(
		string $message = 'Method not allowed',
		int $code = 0,
		Exception|null $previous = null,
		array $allowedMethods = []
	) {
		parent::__construct($message, $code, $previous);
		$this->allowedMethods = array_values(array_map('strtoupper', $allowedMethods));
	}

	/**
	 * Uppercased HTTP method names, suitable for the `Allow:` response header.
	 *
	 * @return array<int, string>
	 */
	public function getAllowedMethods(): array
	{
		return $this->allowedMethods;
	}

}
