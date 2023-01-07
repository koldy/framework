<?php declare(strict_types=1);

namespace Koldy\Filesystem;

use Koldy\Application;
use Koldy\Filesystem\Exception as FilesystemException;

/**
 * Class for manipulation with directories on server
 *
 */
class Directory
{

	/**
	 * Get the list of all files and folders from the given folder
	 *
	 * @param string $path the directory path to read
	 * @param string|null $filter [optional] regex for filtering the list
	 *
	 * @return array assoc; the key is full path of the file and value is only file name
	 * @throws Exception
	 * @example return array('/var/www/site.tld/folder/croatia.png' => 'croatia.png')
	 */
    public static function read(string $path, string $filter = null): array
    {
        if (is_dir($path) && $handle = opendir($path)) {
            $files = [];

            if (!str_ends_with($path, '/')) {
                $path .= '/';
            }

            while (false !== ($entry = readdir($handle))) {
                if ($entry !== '.' && $entry !== '..') {
                    if ($filter === null || preg_match($filter, $entry)) {
                        $fullPath = $path . $entry;

                        if (str_starts_with($fullPath, '.')) {
                            $fullPath = stream_resolve_include_path($path . $entry);
                        }

                        $files[$fullPath] = $entry;
                    }
                }
            }

            return $files;
        } else {
            throw new FilesystemException("Unable to open directory on path={$path}");
        }
    }

	/**
	 * Get the list of all files in in given folder, but with no subdirectories
	 *
	 * @param string $path the directory path to read
	 * @param string|null $filter [optional] regex for filtering the list
	 *
	 * @return array assoc; the key is full path of the file and value is only file name
	 * @throws Exception
	 * @example return array('/var/www/site.tld/folder/croatia.png' => 'croatia.png')
	 */
    public static function readFiles(string $path, string $filter = null): array
    {
        if (is_dir($path) && $handle = opendir($path)) {
            $files = [];

            // append slash if path doesn't contain slash
            if (!str_ends_with($path, '/')) {
                $path .= '/';
            }

            while (false !== ($entry = readdir($handle))) {
                if ($entry !== '.' && $entry !== '..' && !is_dir($path . $entry)) {
                    if ($filter === null || preg_match($filter, $entry)) {
                        $fullPath = $path . $entry;

                        if (str_starts_with($fullPath, '.')) {
                            $fullPath = stream_resolve_include_path($path . $entry);
                        }

                        $files[$fullPath] = $entry;
                    }
                }
            }

            return $files;
        } else {
            throw new FilesystemException("Unable to open directory on path={$path}");
        }
    }

	/**
	 * Get the list of all only files from the given folder and its sub-folders
	 *
	 * @param string $path the directory path to read
	 * @param string|null $filter [optional] regex for filtering the list
	 *
	 * @return array assoc; the key is full path of the file and value is only file name
	 * @throws Exception
	 * @example return array('/var/www/site.tld/folder/croatia.png' => 'croatia.png')
	 */
    public static function readFilesRecursive(string $path, string $filter = null): array
    {
        if (is_dir($path) && $handle = opendir($path)) {
            $files = [];

            // append slash if path doesn't contain slash
            if (!str_ends_with($path, '/')) {
                $path .= '/';
            }

            while (false !== ($entry = readdir($handle))) {
                if ($entry !== '.' && $entry !== '..') {
                    if (!is_dir($path . $entry)) {
                        if ($filter === null || preg_match($filter, $entry)) {
                            $fullPath = $path . $entry;

                            if (str_starts_with($fullPath, '.')) {
                                $fullPath = stream_resolve_include_path($path . $entry);
                            }

                            $files[$fullPath] = $entry;
                        }
                    } else {
                        // it is sub-directory
                        $files = array_merge($files, static::readFilesRecursive($path . $entry . '/', $filter));
                    }
                }
            }

            return $files;
        } else {
            throw new FilesystemException("Unable to open directory on path={$path}");
        }
    }

    /**
     * Create the target directory recursively if needed
     *
     * @param string $path
     * @param int $chmod default 0644
     *
     * @return void
     * @throws Exception
     * @throws \Koldy\Config\Exception
     * @throws \Koldy\Exception
     * @example $chmod 0777, 0755, 0700
     */
    public static function mkdir(string $path, $chmod = null): void
    {
        if (!is_dir($path)) {
            if ($chmod === null) {
                $chmod = Application::getConfig('application')->getArrayItem('filesystem', 'default_chmod') ?? 0644;
            }

            if (!mkdir($path, $chmod, true)) {
                throw new FilesystemException("Can not create directory on path={$path}");
            }
        }
    }

    /**
     * Remove directory and content inside recursively
     *
     * @param string $directory
     *
     * @throws FilesystemException
     */
    public static function rmdirRecursive(string $directory): void
    {
        if (is_dir($directory)) {
            static::emptyDirectory($directory);

            if (!rmdir($directory)) {
                throw new FilesystemException("Unable to remove directory on path={$directory}");
            }
        }
    }

    /**
     * Empty all directory content, but do not delete the directory
     *
     * @param string $directory
     *
     * @return void
     * @throws FilesystemException
     */
    public static function emptyDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $path) {
                if ($path->isFile()) {
                    if (!unlink($path->getPathname())) {
                        throw new FilesystemException("Unable to empty directory while emptying directory on path={$path->getPathname()}");
                    }
                } else {
                    if (!rmdir($path->getPathname())) {
                        throw new FilesystemException("Unable to empty directory while emptying directory on path={$path->getPathname()}");
                    }
                }
            }
        }
    }

}
