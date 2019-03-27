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
     * @var string
     */
    protected $savePath = null;

    /**
     * The 'options' part from config/session.php
     *
     * @var array
     */
    protected $config = [];

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
     * @param string $save_path
     * @param string $sessionid
     *
     * @return bool
     */
    public function open($save_path, $sessionid)
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
    public function close()
    {
        return true;
    }

    /**
     * @param string $sessionid
     *
     * @return string
     */
    public function read($sessionid)
    {
        return (string)@file_get_contents("{$this->savePath}{$sessionid}.txt");
    }

	/**
	 * @param string $sessionid
	 * @param string $sessiondata
	 *
	 * @return bool
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Exception
	 * @throws \Koldy\Filesystem\Exception
	 */
    public function write($sessionid, $sessiondata)
    {
        $wasWritten = !(file_put_contents("{$this->savePath}{$sessionid}.txt", $sessiondata) === false);

        if (!$wasWritten) {
            // maybe there's no folder on file system?

            if (!is_dir($this->savePath)) {
                Directory::mkdir($this->savePath, 0755);
                $wasWritten = !(file_put_contents("{$this->savePath}{$sessionid}.txt", $sessiondata) === false);
            }
        }

        return $wasWritten;
    }

    /**
     * @param string $sessionid
     *
     * @return bool
     */
    public function destroy($sessionid)
    {
        $file = "{$this->savePath}{$sessionid}.txt";
        if (file_exists($file)) {
            unlink($file);
        }

        return true;
    }

    /**
     * @param int $maxlifetime
     *
     * @return bool
     */
    public function gc($maxlifetime)
    {
        foreach (glob("{$this->savePath}*") as $file) {
            if (filemtime($file) + $maxlifetime < time() && file_exists($file)) {
                unlink($file);
            }
        }

        return true;
    }

}
