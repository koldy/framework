<?php declare(strict_types = 1);

namespace Koldy\Log\Adapter;

use Koldy\Application;
use Koldy\Config\Exception as ConfigException;
use Koldy\Log\Exception;
use Koldy\Log;
use Koldy\Filesystem\Directory;
use Koldy\Log\Message;

/**
 * This log writer will print all log messages into file on file system. Its
 * smart enough to open the file just once and close it when request execution
 * completes.
 *
 * @link http://koldy.net/docs/log/file
 */
class File extends AbstractLogAdapter
{

    /**
     * The file pointer
     *
     * @var resource
     */
    private $fp = null;

    /**
     * The last file pointer file name for log
     *
     * @var string
     */
    private $fpFile = null;

    /**
     * Flag if file pointer was already closed
     *
     * @var boolean
     */
    private $closed = false;

    /**
     * Get message function handler
     *
     * @var \Closure
     */
    protected $getMessageFunction = null;

    /**
     * Construct the handler to log to files. The config array will be check
     * because all configs are strict
     *
     * @param array $config
     *
     * @throws ConfigException
     */
    public function __construct(array $config)
    {
        if (isset($config['get_message_fn'])) {
            if ($config['get_message_fn'] instanceof \Closure) {
                $this->getMessageFunction = $config['get_message_fn'];
            } else {

                if (is_object($config['get_message_fn'])) {
                    $got = get_class($config['get_message_fn']);
                } else {
                    $got = gettype($config['get_message_fn']);
                }

                throw new ConfigException('Invalid get_message_fn type; expected \Closure object, got: ' . $got);
            }
        }

        $self = $this;

        register_shutdown_function(function () use ($self) {
            if (!isset($self->config['dump'])) {
                return;
            }

            $dump = $self->config['dump'];

            // 'speed', 'included_files', 'include_path', 'whitespace'

            if (in_array('speed', $dump)) {
                $method = isset($_SERVER['REQUEST_METHOD']) ? ($_SERVER['REQUEST_METHOD'] . '=' . Application::getCurrentURL()) : ('CLI=' . Application::getCliName());

                $executedIn = Application::getRequestExecutionTime();
                $self->logMessage(new Message('notice', $method . ' EXECUTED IN ' . $executedIn . 'ms, used ' . count(get_included_files()) . ' files'));
            }

            if (in_array('whitespace', $dump)) {
                $self->logMessage(new Message('notice', str_repeat('-', 40) . "\n\n\n"));
            }

            if ($self->fp !== null) {
                @fclose($self->fp);

                $self->fp = null;
                $self->fpFile = null;
                $self->closed = true;
            }
        });

        parent::__construct($config);
    }

    /**
     * Get the name of log file
     *
     * @return string
     */
    protected function getFileName(): string
    {
        return gmdate('Y-m-d') . '.log';
    }

    /**
     * Actually log message to file
     *
     * @param Message $message
     *
     * @throws Exception
     * @internal param string $level
     */
    public function logMessage(Message $message): void
    {
        if (in_array($message->getLevel(), $this->config['log'])) {
            // If script is running for very long time (e.g. CRON), then date might change if time passes midnight.
            // In that case, log will continue to write messages to new file.

            $fpFile = $this->getFileName();
            if ($fpFile !== $this->fpFile) {
                //date has changed? or we need to init?
                if ($this->fp) {
                    // close pointer to old file
                    @fclose($this->fp);
                }

                if ($this->config['path'] === null) {
                    $path = Application::getStoragePath('log' . DS . $fpFile);
                } else {
                    $path = str_replace(DS . DS, DS, $this->config['path'] . DS . $fpFile);
                }

                $this->fpFile = $fpFile;

                if (!($this->fp = @fopen($path, 'a'))) {
                    // file failed to open, maybe directory doesn't exists?
                    $dir = dirname($path);
                    if (!is_dir($dir)) {
                        Directory::mkdir($dir, 0755);
                    }

                    if (!($this->fp = @fopen($path, 'a'))) {
                        // now the directory should exists, so try to open file again
                        throw new Exception('Can not write to log file on path=' . $path);
                    }
                }
            }

            if (!$this->fp || $this->fp === null) {
                throw new Exception("Can not write to log file: {$fpFile}");
            }

            if ($this->getMessageFunction !== null) {
                $line = call_user_func($this->getMessageFunction, $message);
            } else {
                $who = $message->getWho() ?? Log::getWho();
                $line = "{$message->getTime()->format('Y-m-d H:i:sO')}\t{$message->getLevel()}\t{$who}\t{$message->getMessage()}\n";
            }

            if (!@fwrite($this->fp, $line)) { // actually write it in file
                throw new Exception('Unable to write to log file');
            } else {

                // so, writing was ok, but what if showdown was already called?
                // be sent any more - sorry

                if ($this->closed) {
                    @fclose($this->fp);
                    $this->fp = null;
                    $this->fpFile = null;
                }
            }
        }
    }

}
