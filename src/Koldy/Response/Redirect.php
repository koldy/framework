<?php declare(strict_types=1);

namespace Koldy\Response;

use Koldy\Application;
use Koldy\Response\Exception as ResponseException;

/**
 * Perform redirect be flushing redirect headers to client. Usually, you'll use
 * this class as return value from method in your controller classes.
 *
 * @example
 *
 *    class PageController {
 *      public function userAction() {
 *        return Redirect::href('user', 'list');
 *      }
 *    }
 *
 * @link http://koldy.net/docs/redirect
 *
 */
class Redirect extends AbstractResponse
{

    /**
     * Permanent redirect (301) to the given URL
     *
     * @param string $where
     *
     * @return Redirect
     * @link http://koldy.net/docs/redirect#methods
     */
    public static function permanent(string $where): Redirect
    {
        /** @var \Koldy\Response\Redirect $self */
        $self = new static();
        $self->statusCode(301)->setHeader('Location', $where)//->setHeader('Status', '301 Moved Permanently')
          ->setHeader('Connection', 'close')->setHeader('Content-Length', 0);

        return $self;
    }

    /**
     * Temporary redirect (302) to the given URL
     *
     * @param string $where
     *
     * @return Redirect
     * @link http://koldy.net/docs/redirect#methods
     */
    public static function temporary(string $where): Redirect
    {
        /** @var Redirect $self */
        $self = new static();
        $self->statusCode(302)->setHeader('Location', $where)//->setHeader('Status', '302 Moved Temporary')
          ->setHeader('Connection', 'close')->setHeader('Content-Length', 0);

        return $self;
    }

    /**
     * Alias to temporary() method
     *
     * @param string $where
     *
     * @return Redirect
     * @example http://www.google.com
     * @link http://koldy.net/docs/redirect#methods
     */
    public static function to(string $where): Redirect
    {
        return static::temporary($where);
    }

    /**
     * Redirect user to home page
     *
     * @return Redirect
     * @link http://koldy.net/docs/redirect#usage
     */
    public static function home(): Redirect
    {
        return static::href();
    }

    /**
     * Redirect user to the URL generated with Route::href method
     *
     * @param string $controller [optional]
     * @param string $action [optional]
     * @param array $params [optional]
     *
     * @return Redirect
     * @link http://koldy.net/docs/redirect#usage
     * @link http://koldy.net/docs/url#href
     */
    public static function href(string $controller = null, string $action = null, array $params = null): Redirect
    {
        return static::temporary(Application::route()->href($controller, $action, $params));
    }

    /**
     * Redirect user the the given link under the same domain.
     *
     * @param string $path
     * @param string|null $assetSite
     *
     * @return Redirect
     * @link http://koldy.net/docs/redirect#usage
     * @link http://koldy.net/docs/url#link
     */
    public static function link(string $path, string $assetSite = null): Redirect
    {
        return self::temporary(Application::route()->asset($path, $assetSite));
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
