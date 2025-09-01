<?php declare(strict_types=1);

namespace Koldy\Session\Adapter;

use Koldy\Application;
use Koldy\Filesystem\Directory;
use SessionHandlerInterface;

/**
 * This is session handler that will store session files to the local
 * storage folder. You MUSTN'T use it! This class will use PHP internally
 * by it self. You just configure it all and watch the magic.
 *
 * @link https://koldy.net/framework/docs/2.0/session/file.md
 */
class File implements SessionHandlerInterface
{

    /**
     * The directory where files will be stored
     *
     * @var string|null
     */
    protected string | null $savePath = null;

    /**
     * The 'options' part from config/session.php
     *
     * @var array
     */
    protected array $config;

    /**
     * Construct the File Session storage handler
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * @param string $path
     * @param string $name
     *
     * @return bool
     */
    public function open(string $path, string $name): bool
    {
        // we'll ignore $save_path because we have our own path from config

        if (isset($this->config['session_save_path'])) {
            $this->savePath = $this->config['session_save_path'];
        } else {
            $this->savePath = Application::getStoragePath('session');
        }

        if (substr($this->savePath, -1) != DS) {
            $this->savePath .= DS;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * @param string $id
     *
     * @return string
     */
    public function read(string $id): string
    {
        return (string)@file_get_contents("{$this->savePath}{$id}.txt");
    }

	/**
	 * @param string $id
	 * @param string $data
	 *
	 * @return bool
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Exception
	 * @throws \Koldy\Filesystem\Exception
	 */
    public function write(string $id, string $data): bool
    {
        $wasWritten = !(file_put_contents("{$this->savePath}{$id}.txt", $data) === false);

        if (!$wasWritten) {
            // maybe there's no folder on file system?

            if (!is_dir($this->savePath)) {
                Directory::mkdir($this->savePath, 0755);
                $wasWritten = !(file_put_contents("{$this->savePath}{$id}.txt", $data) === false);
            }
        }

        return $wasWritten;
    }

    /**
     * @param string $id
     *
     * @return bool
     */
    public function destroy(string $id): bool
    {
        $file = "{$this->savePath}{$id}.txt";
        if (file_exists($file)) {
            unlink($file);
        }

        return true;
    }

    /**
     * @param int $max_lifetime
     *
     * @return int|false
     */
    public function gc(int $max_lifetime): int | false
    {
		$list = glob("{$this->savePath}*");

		if ($list === false) {
			return false;
		}

		$counter = 0;

        foreach ($list as $file) {
            if (filemtime($file) + $max_lifetime < time() && file_exists($file) && unlink($file)) {
				$counter++;
            }
        }

        return $counter;
    }

}
