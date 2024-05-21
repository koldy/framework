<?php declare(strict_types=1);

namespace Koldy\Response;

use Koldy\Application;
use Koldy\Exception;
use Koldy\Route;

/**
 * Response Redirection client to another Location.
 *
 */
class Redirect extends AbstractResponse
{

    /**
     * Permanent redirect (301) to the given URL
     *
     * @param string $where
     *
     * @return $this
     */
    public static function permanent(string $where): Redirect
    {
        $self = new static();
        $self->statusCode(301)
          ->setHeader('Location', $where)
          ->setHeader('Connection', 'close')
          ->setHeader('Content-Length', 0);

        return $self;
    }

    /**
     * Temporary redirect (302) to the given URL
     *
     * @param string $where
     *
     * @return $this
     */
    public static function temporary(string $where): Redirect
    {
        $self = new static();
        $self->statusCode(302)
          ->setHeader('Location', $where)
          ->setHeader('Connection', 'close')
          ->setHeader('Content-Length', 0);

        return $self;
    }

    /**
     * Alias to temporary() method (302)
     *
     * @param string $where
     *
     * @return $this
     * @example http://www.google.com
     */
    public static function to(string $where): Redirect
    {
        return static::temporary($where);
    }

	/**
	 * Redirect client (302) to home page
	 *
	 * @return $this
	 * @throws Exception
	 */
    public static function home(): Redirect
    {
        return static::href();
    }

	/**
	 * Redirect client (302) to the URL generated with Route::href method
	 *
	 * @param string|null $controller
	 * @param string|null $action
	 * @param array|null $params
	 *
	 * @return $this
	 * @throws \Koldy\Exception
	 */
    public static function href(string $controller = null, string $action = null, array $params = null): Redirect
    {
        return static::temporary(Application::route()->href($controller, $action, $params));
    }

	/**
	 * Redirect client the the given link under the same domain.
	 *
	 * @param string $path
	 * @param string|null $assetSite
	 *
	 * @return $this
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Exception
	 * @deprecated use asset() method instead of this mthod
	 */
    public static function link(string $path, string $assetSite = null): Redirect
    {
        return self::temporary(Application::route()->asset($path, $assetSite));
    }

	/**
	 * Redirect client to asset URL (defined by key in mandatory config under assets)
	 *
	 * @param string $path
	 * @param string|null $assetKey
	 *
	 * @return $this
	 * @throws Route\Exception
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Exception
	 */
    public static function asset(string $path, string $assetKey = null): Redirect
    {
        return self::temporary(Route::asset($path, $assetKey));
    }

    /**
     * Run Redirect
     */
    public function flush(): void
    {
        $this->prepareFlush();
        $this->runBeforeFlush();

        $this->setHeader('Content-Length', 0);
        $this->flushHeaders();
        flush();

        $this->runAfterFlush();
    }

}
