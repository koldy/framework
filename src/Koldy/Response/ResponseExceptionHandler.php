<?php declare(strict_types=1);

namespace Koldy\Response;

use Koldy\Application;
use Koldy\Log;
use Koldy\Json;
use Koldy\Response\Exception\BadRequestException;
use Koldy\Response\Exception\ForbiddenException;
use Koldy\Response\Exception\NotFoundException;
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
     * @throws Json\Exception
     */
    protected function handleExceptionInAjax(Throwable $e): void
    {
        $data = [
          'success' => false,
          'type' => 'exception',
          'exception' => !Application::isLive() ? $e->getMessage() : null,
          'trace' => !Application::isLive() ? $e->getTrace() : null
        ];

        if ($e instanceof BadRequestException) {
            http_response_code(400);

        } else if ($e instanceof ValidatorException) {
            http_response_code(400);
            $data['messages'] = $e->getValidator()->getMessages();

        } else if ($e instanceof NotFoundException) {
            http_response_code(404);

        } else if ($e instanceof ForbiddenException) {
            http_response_code(403);

        } else {
            try {
                Log::emergency($e);
            } catch (Log\Exception $e) {
                // we can't handle this
            }
            http_response_code(503);

        }

        print Json::encode($data);
    }

    /**
     * @param Throwable $e
     * @throws Exception
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

            if ($e instanceof BadRequestException || $e instanceof ValidatorException) {
                http_response_code(400);

            } else if ($e instanceof NotFoundException) {
                http_response_code(404);

            } else if ($e instanceof ForbiddenException) {
                http_response_code(403);

            } else {
                try {
                    Log::emergency($e);
                } catch (Log\Exception $e) {
                    // we can't handle this
                }
                http_response_code(503);

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
     * @throws Exception
     * @throws Json\Exception
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