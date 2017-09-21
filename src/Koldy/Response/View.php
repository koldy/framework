<?php declare(strict_types=1);

namespace Koldy\Response;

use Koldy\Application;
use Koldy\Data;
use Koldy\Log;
use Koldy\Response\Exception as ResponseException;

/**
 * The view class will properly serve prepared HTML to user.
 *
 * This framework doesn't have and doesn't use any template engine so there is
 * no need to learn extra syntax or what so ever. All you need to know is how to
 * set up file structure.
 *
 * View files should contain all of your HTML code and should never do any logic
 * or data fetching. Try to keep your code clean and in MVC style.
 *
 * @link http://koldy.net/docs/view
 */
class View extends AbstractResponse
{

    use Data;

    /**
     * View file that will be rendered
     *
     * @var string
     */
    protected $view = null;

    public function __construct(string $view)
    {
        $this->setView($view);
    }

    /**
     * Create the object with base view
     *
     * @param string $view
     *
     * @return View
     * @example View::create('base') will initialize /application/views/base.phtml
     * @link http://koldy.net/docs/view
     */
    public static function create(string $view): View
    {
        return new static($view);
    }

    /**
     * Set view after object initialization
     *
     * @param string $view
     *
     * @return View
     */
    public function setView(string $view): View
    {
        $this->view = $view;
        return $this;
    }

    /**
     * Get the path of the view
     *
     * @param string $view
     *
     * @return string
     */
    protected static function getViewPath(string $view): string
    {
        if (DS != '/') {
            $view = str_replace('/', DS, $view);
        }

        $pos = strpos($view, ':');
        if ($pos === false) {
            return Application::getViewPath() . $view . '.phtml';
        } else {
            // TODO: Fix module part
            return dirname(substr(Application::getViewPath(), 0, -1)) . DS . 'modules' . DS . substr($view, 0, $pos) . DS . 'views' . DS . substr($view, $pos + 1) . '.phtml';
        }
    }

    /**
     * Does view exists or not
     *
     * @param string $view
     *
     * @return boolean
     */
    public static function exists(string $view): bool
    {
        $path = static::getViewPath($view);
        return is_file($path);
    }

    /**
     * Render some other view file inside of parent view file
     *
     * @param string $view
     * @param array $with php variables
     *
     * @throws Exception
     * @return string
     */
    public function render(string $view, array $with = null): string
    {
        $path = static::getViewPath($view);

        if (!file_exists($path)) {
            Log::alert("Can not find view on path={$path}");
            throw new ResponseException("View ({$view}) not found");
        }

        if ($with !== null && count($with) > 0) {
            foreach ($with as $variableName => $value) {
                if (!is_string($variableName)) {
                    throw new ResponseException('Invalid argument name, expected string, got ' . gettype($variableName));
                }

                $$variableName = $value;
            }
        }

        ob_start();
        include($path);
        return ob_get_clean();
    }

    /**
     * Render view if exists on filesystem - if it doesn't exists, it won't throw any error
     *
     * @param string $view
     * @param array $with
     *
     * @return string
     */
    public function renderViewIf(string $view, array $with = null): string
    {
        if ($this->exists($view)) {
            return $this->render($view, $with);
        } else {
            return '';
        }
    }

    /**
     * Render view from key variable if exists - if it doesn't exists, it won't throw any error
     *
     * @param string $key
     * @param array $with
     *
     * @return string
     */
    public function renderViewInKeyIf(string $key, array $with = null): string
    {
        if (!$this->has($key)) {
            return '';
        }

        $view = $this->$key;

        if (static::exists($view)) {
            return $this->render($view, $with);
        } else {
            return '';
        }
    }

    /**
     * Print variable content
     *
     * @param string $key
     */
    public function printIf(string $key): void
    {
        if ($this->has($key)) {
            print $this->$key;
        }
    }

    /**
     * Flush the content we collected to OB. This part is here so you
     * can override it if needed.
     */
    protected function flushBuffer(): void
    {
        $this->flushHeaders();
        ob_end_flush();
        flush();
    }

    /**
     * This method is called by framework, but in some cases, you'll want to call it by yourself.
     *
     * @throws Exception
     */
    public function flush(): void
    {
        $this->prepareFlush();
        $this->runBeforeFlush();

        $path = static::getViewPath($this->view);

        if (!file_exists($path)) {
            Log::error("Can not find view on path={$path}");
            throw new ResponseException("View ({$this->view}) not found");
        }

        ob_start();

        include($path);
        $size = ob_get_length();
        $this->setHeader('Content-Length', $size);

        $this->flushBuffer();

        $this->runAfterFlush();
    }

    /**
     * Get the rendered view code.
     *
     * @throws Exception
     * @return string
     * @link http://koldy.net/docs/view#get-output
     */
    public function getOutput(): string
    {
        $this->prepareFlush();
        $path = static::getViewPath($this->view);

        if (!file_exists($path)) {
            Log::error("Can not find view on path={$path}");
            throw new ResponseException("View ({$this->view}) not found");
        }

        ob_start();
        include($path);
        return ob_get_clean();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getOutput();
    }

}
