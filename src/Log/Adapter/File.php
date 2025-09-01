<?php declare(strict_types=1);

namespace Koldy\Log\Adapter;

use Closure;
use Koldy\Application;
use Koldy\Config\Exception as ConfigException;
use Koldy\Filesystem\Directory;
use Koldy\Log;
use Koldy\Log\Exception;
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
	 * @var resource|null
	 */
	protected mixed $fp = null;

	/**
	 * The last file pointer file name for log
	 *
	 * @var string|null
	 */
	protected string|null $fpFile = null;

	/**
	 * @var string|null
	 */
	protected string|null $generatedFileName = null;

	/**
	 * Get message function handler
	 *
	 * @var Closure|null
	 */
	protected Closure|null $getMessageFunction = null;

	/**
	 * Function for getting the file name
	 *
	 * @var Closure|null
	 */
	protected Closure|null $fileNameFn = null;

	/**
	 * @var int|null
	 */
	protected int|null $mode = null;

	/**
	 * @var int|null
	 */
	protected int|null $fileMode = null;

	/**
	 * Construct the handler to log to files. The config array will be check
	 * because all configs are strict
	 *
	 * @param array $config
	 *
	 * @throws ConfigException
	 * @throws \Koldy\Convert\Exception
	 * @throws \Koldy\Exception
	 */
	public function __construct(array $config)
	{
		if (isset($config['get_message_fn'])) {
			if ($config['get_message_fn'] instanceof Closure) {
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

		if (isset($config['file_name_fn'])) {
			if ($config['file_name_fn'] instanceof Closure) {
				$this->fileNameFn = $config['file_name_fn'];
			} else {

				if (is_object($config['file_name_fn'])) {
					$got = get_class($config['file_name_fn']);
				} else {
					$got = gettype($config['file_name_fn']);
				}

				throw new ConfigException('Invalid file_name_fn type; expected \Closure object, got: ' . $got);
			}
		}

		if (!isset($config['log'])) {
			throw new ConfigException('The \'log\' key has to be defined in every log configuration block');
		}

		if (!isset($config['path'])) {
			$config['path'] = null;
		}

		if (isset($config['mode'])) {
			$this->mode = $config['mode'];
		}

		if (isset($config['file_mode'])) {
			$this->fileMode = $config['file_mode'];
		}

		parent::__construct($config);

		$self = $this;

		register_shutdown_function(function () use ($self) {
			$self->dump();

			if ($self->fp !== null) {
				@fclose($self->fp);

				$self->fp = null;
				$self->fpFile = null;
			}
		});
	}

	/**
	 * Actually log message to file
	 *
	 * @param Message $message
	 *
	 * @throws ConfigException
	 * @throws Exception
	 * @throws \Koldy\Exception
	 * @throws \Koldy\Filesystem\Exception
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
				$dir = dirname($path);

				// if file_mode is set, then check the existance of file and change the chmod on file creation
				if ($this->fileMode !== null && !is_file($path) && is_dir($dir)) {
					// directory exists, but file doesn't
					touch($path);
					chmod($path, $this->fileMode);
					clearstatcache(); // delete the cache so the next pass of is_file caches the correct result
				}

				if (!($this->fp = @fopen($path, 'a'))) {
					// file failed to open, maybe directory doesn't exists?
					if (!is_dir($dir)) {
						Directory::mkdir($dir, $this->mode);
					}

					if ($this->fileMode !== null && !is_file($path)) {
						touch($path);
						chmod($path, $this->fileMode);
						clearstatcache(); // delete the cache so the next pass of is_file caches the correct result
					}

					if (!($this->fp = @fopen($path, 'a'))) {
						// now the directory should exists, so try to open file again
						throw new Exception('Can not write to log file on path=' . $path);
					}
				}
			}

			// @phpstan-ignore-next-line
			if ($this->fp === false || $this->fp === null) {
				if ($this->config['path'] === null) {
					$path = Application::getStoragePath('log' . DS . $fpFile);
				} else {
					$path = str_replace(DS . DS, DS, $this->config['path'] . DS . $fpFile);
				}

				throw new Exception("Can not write to log file path={$path}");
			}

			if ($this->getMessageFunction !== null) {
				$line = call_user_func($this->getMessageFunction, $message);
			} else {
				$time = $message->getTime()->format('y-m-d H:i:s.v');
				$level = strtoupper($message->getLevel());
				$space = str_repeat(' ', 10 - strlen($level));
				$who = $message->getWho() ?? Log::getWho();
				$line = "{$time} {$level}{$space}{$who}\t{$message->getMessage()}\n";
			}

			if (!@fwrite($this->fp, $line)) { // actually write it in file
				throw new Exception('Unable to write to log file');
			}
		}
	}

	/**
	 * Get the name of log file
	 *
	 * @return string
	 */
	protected function getFileName(): string
	{
		if ($this->generatedFileName === null || Application::isCli()) {
			if ($this->fileNameFn !== null) {
				$this->generatedFileName = call_user_func($this->fileNameFn);
			} else {
				$this->generatedFileName = gmdate('Y-m-d') . '.log';
			}
		}

		return $this->generatedFileName;
	}

}
