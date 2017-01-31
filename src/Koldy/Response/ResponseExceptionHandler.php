<?php declare(strict_types = 1);

namespace Koldy\Response;

use Koldy\Application;
use Koldy\Log;
use Koldy\Json;
use Koldy\Response\Exception\BadRequestException;
use Koldy\Response\Exception\ForbiddenException;
use Koldy\Response\Exception\NotFoundException;
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

    /**
     * @var \Exception
     */
    protected $e;

    public function __construct(Throwable $e)
    {
        $this->e = $e;
    }

    /**
     * @param Throwable $e
     */
    protected function handleExceptionInAjax(Throwable $e): void
    {
        $data = [
          'success' => false,
          'type' => 'exception',
          'exception' => !Application::isLive() ? $e->getMessage() : null,
          'trace' => !Application::isLive() ? $e->getTraceAsString() : null
        ];

        if ($e instanceof BadRequestException) {
            http_send_status(400);

        } else if ($e instanceof NotFoundException) {
            http_send_status(404);

        } else if ($e instanceof ForbiddenException) {
            http_send_status(403);

        } else {
            http_send_status(503);

        }

        print Json::encode($data);
    }

    /**
     * @param Throwable $e
     */
    protected function handleExceptionInNormalRequest(Throwable $e): void
    {
        if (View::exists('error')) {
            $view = View::create('error');
            $view->set('e', $e);

            if ($e instanceof BadRequestException) {
                Log::debug($e);
                $view->statusCode(400);

            } else if ($e instanceof NotFoundException) {
                Log::debug($e);
                $view->statusCode(404);

            } else if ($e instanceof ForbiddenException) {
                Log::notice($e);
                $view->statusCode(403);

            } else {
                Log::emergency($e);
                $view->statusCode(503);

            }

            $view->flush();
        } else {
            // we don't have a view for exception handling, let's return something

            if ($e instanceof BadRequestException) {
                Log::debug($e);
                http_send_status(400);

            } else if ($e instanceof NotFoundException) {
                Log::debug($e);
                http_send_status(404);

            } else if ($e instanceof ForbiddenException) {
                Log::debug($e);
                http_send_status(403);

            } else {
                Log::emergency($e);
                http_send_status(503);

            }

            if (!Application::isLive()) {
                echo "<strong>{$e->getMessage()}</strong><pre>{$e->getTraceAsString()}</pre>";
            } else {
                echo "<h1>Error</h1><p>Something went wrong. Please try again later!</p>";
            }
        }
    }

    /**
     * Execute exception handler
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