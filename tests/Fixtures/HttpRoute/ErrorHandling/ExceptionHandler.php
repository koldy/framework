<?php declare(strict_types=1);

namespace Tests\Fixtures\HttpRoute\ErrorHandling;

use Throwable;

/**
 * Minimal exception handler for tests — avoids the default ResponseExceptionHandler
 * which requires session config and outputs HTTP responses.
 */
class ExceptionHandler
{

	public Throwable $exception;

	public static bool $called = false;

	public function __construct(Throwable $e)
	{
		$this->exception = $e;
	}

	public function exec(): void
	{
		self::$called = true;
	}

}

