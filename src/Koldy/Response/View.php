<?php declare(strict_types=1);

namespace Koldy\Response;

use Koldy\Application;
use Koldy\Data;
use Koldy\Response\Exception as ResponseException;

/**
 * The view class will properly serve prepared HTML to user.
 *
 * This framework doesn't have and doesn't use any template engine so there is
 * no need to learn extra syntax or anything. All you need to know is how to
 * set up file structure.
 *
 * View files should contain all of your HTML code and should never do any logic
 * or data fetching. Try to keep your code clean and in MVC style.
 *
 * @link http://koldy.net/docs/view
 * @phpstan-consistent-constructor
 */
class View extends AbstractResponse
{

    use Data;

    /**
     * View file that will be rendered
     *
     * @var string
     */
    protected string $view;

    /**
     * @var string
     */
    protected string $viewPath;

    /**
     * View constructor.
     *
     * @param string $view
     */
    public function __construct(string $view)
    {
        $this->viewPath = Application::getViewPath();
        $this->setView($view);
    }

    /**
     * Create the object with base view
     *
     * @param string $view
     *
     * @return static
     * @example View::create('base') will initialize /application/views/base.phtml
     * @link http://koldy.net/docs/view
     */
    public static function create(string $view): self
    {
        return new static($view);
    }

    /**
     * Set view after object initialization
     *
     * @param string $view
     *
     * @return static
     */
    public function setView(string $view): self
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
    protected function getViewPath(string $view): string
    {
		// @phpstan-ignore-next-line
        if (DS !== '/') {
            $view = str_replace('/', DS, $view);
        }

        $pos = strpos($view, ':');
        if ($pos === false) {
            return $this->viewPath . $view . '.phtml';
        } else {
            return dirname(substr($this->viewPath, 0, -1)) . DS . 'modules' . DS . substr($view, 0, $pos) . DS . 'views' . DS . substr($view, $pos + 1) . '.phtml';
        }
    }

    /**
     * Set custom view path where views are stored. Any additional use within this instance will be relative to the
     * path given here
     *
     * @param string $basePath
     *
     * @return static
     */
    public function setViewPath(string $basePath): self
    {
        $this->viewPath = $basePath;

        if (!str_ends_with($this->viewPath, '/')) {
            $this->viewPath .= '/';
        }

        return $this;
    }

    /**
     * Does view exists or not in main view path
     *
     * @param string $view
     *
     * @return boolean
     */
    public static function exists(string $view): bool
    {
        $pos = strpos($view, ':');

        if ($pos === false) {
            $path = Application::getViewPath() . $view . '.phtml';
        } else {
            $path = dirname(substr(Application::getViewPath(), 0, -1)) . DS . 'modules' . DS . substr($view, 0, $pos) . DS . 'views' . DS . substr($view, $pos + 1) . '.phtml';
        }


        return is_file($path);
    }

	/**
	 * Render some other view file inside of parent view file
	 *
	 * @param string $view
	 * @param array|null $with php variables
	 *
	 * @return string
	 * @throws Exception
	 */
    public function render(string $view, array|null $with = null): string
    {
        $path = $this->getViewPath($view);

        if (!file_exists($path)) {
            throw new ResponseException("View ({$view}) not found on path={$path}");
        }

        if ($with !== null && count($with) > 0) {
            foreach ($with as $variableName => $value) {
                if (!is_string($variableName)) {
                    throw new ResponseException('Invalid argument name, expected string, got ' . gettype($variableName));
                }

                $$variableName = $value;
            }
        }

        ob_start(null, 0, PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_FLUSHABLE | PHP_OUTPUT_HANDLER_REMOVABLE);

        require $path;

        return ob_get_clean();
    }

	/**
	 * Render view if exists on filesystem - if it doesn't exists, it won't throw any error
	 *
	 * @param string $view
	 * @param array|null $with
	 *
	 * @return string
	 * @throws Exception
	 */
    public function renderViewIf(string $view, array|null $with = null): string
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
	 * @param array|null $with
	 *
	 * @return string
	 * @throws Exception
	 */
    public function renderViewInKeyIf(string $key, array|null $with = null): string
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
	 * @throws \Koldy\Exception
	 */
    public function flush(): void
    {
        $this->prepareFlush();
        $this->runBeforeFlush();

        $path = $this->getViewPath($this->view);

        if (!file_exists($path)) {
            throw new ResponseException("View ({$this->view}) not found on path={$path}");
        }

        ob_start(null, 0, PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_FLUSHABLE | PHP_OUTPUT_HANDLER_REMOVABLE);

        require $path;

	    $statusCode = $this->statusCode;
	    $statusCodeIs1XX = $statusCode >= 100 && $statusCode <= 199;

	    if (!$statusCodeIs1XX && $statusCode !== 204) {
		    $size = ob_get_length();
		    $this->setHeader('Content-Length', $size);
	    }

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
        $path = $this->getViewPath($this->view);

        if (!file_exists($path)) {
            throw new ResponseException("View ({$this->view}) not found on path={$path}");
        }

        ob_start(null, 0, PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_FLUSHABLE | PHP_OUTPUT_HANDLER_REMOVABLE);

        require $path;

        return ob_get_clean();
    }

    /**
     * @return string
     * @throws Exception
     */
    public function __toString()
    {
        return $this->getOutput();
    }

}
