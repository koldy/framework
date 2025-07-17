<?php declare(strict_types=1);

namespace Koldy\Response;

use Koldy\Application;
use Koldy\Exception;
use Koldy\Json;
use Koldy\Log;
use Koldy\Response\Exception\BadRequestException;
use Koldy\Response\Exception\ForbiddenException;
use Koldy\Response\Exception\NotFoundException;
use Koldy\Response\Json as JsonResponse;
use Koldy\Validator\ConfigException;
use Koldy\Validator\Exception as ValidatorException;
use Throwable;

/**
 * Class ResponseExceptionHandler
 *
 * Extend this class within your controllers to control your exceptions
 *
 * @package Koldy\Response
 */
class ResponseExceptionHandler
{

	public function __construct(protected Throwable $e)
	{
	}

	/**
	 * @param Throwable $e
	 *
	 * @throws ValidatorException
	 * @throws Exception
	 * @throws ConfigException
	 */
	protected function handleExceptionInAjax(Throwable $e): void
	{
		$response = JsonResponse::create([
			'success' => false,
			'type' => 'exception',
			'exception' => !Application::isLive() ? $e->getMessage() : null,
			'trace' => !Application::isLive() ? $e->getTrace() : null
		]);

		if ($e instanceof BadRequestException) {
			$response->statusCode(400);

		} else if ($e instanceof ValidatorException) {
			$response->statusCode(400);
			$response->set('messages', $e->getValidator()->getMessages());

		} else if ($e instanceof NotFoundException) {
			$response->statusCode(404);

		} else if ($e instanceof ForbiddenException) {
			$response->statusCode(403);

		} else {
			try {
				Log::emergency($e);
			} catch (Log\Exception $ignored) {
				// we can't handle this
			}
			$response->statusCode(503);

		}

		$response->flush();
	}

	/**
	 * @param Throwable $e
	 *
	 * @throws Exception
	 * @throws \Koldy\Exception
	 */
	protected function handleExceptionInNormalRequest(Throwable $e): void
	{
		if (View::exists('error')) {
			$view = View::create('error');

			if ($e instanceof BadRequestException || $e instanceof ValidatorException) {
				$view->statusCode(400);

			} else if ($e instanceof NotFoundException) {
				$view->statusCode(404);

			} else if ($e instanceof ForbiddenException) {
				$view->statusCode(403);

			} else {
				try {
					Log::emergency($e);
				} catch (Log\Exception $e) {
					// we can't handle this
				}
				$view->statusCode(503);
			}

			$view->set('e', $e);
			$view->flush();
		} else {
			// we don't have a view for exception handling, let's return something
			$plain = Plain::create();

			if ($e instanceof BadRequestException || $e instanceof ValidatorException) {
				$plain->statusCode(400);

			} else if ($e instanceof NotFoundException) {
				$plain->statusCode(404);

			} else if ($e instanceof ForbiddenException) {
				$plain->statusCode(403);

			} else {
				try {
					Log::emergency($e);
				} catch (Throwable $e) {
					// we can't handle this
				}
				$plain->statusCode(503);
			}

			if (!Application::isLive()) {
				$plain->setContent("<strong>{$e->getMessage()}</strong><pre>{$e->getTraceAsString()}</pre>");
			} else {
				$plain->setContent("<h1>Error</h1><p>Something went wrong. Please try again later!</p>");
			}

			$plain->flush();
		}
	}

	/**
	 * Execute exception handler
	 * @throws Exception
	 * @throws Json\Exception
	 * @throws ValidatorException
	 * @throws \Koldy\Exception
	 * @throws \Koldy\Validator\ConfigException
	 */
	public function exec(): void
	{
		if (Application::route()->isAjax()) {
			// if is ajax
			$this->handleExceptionInAjax($this->e);
		} else {
			// normal request
			$this->handleExceptionInNormalRequest($this->e);
		}
	}

}
