<?php declare(strict_types=1);

namespace Koldy;

/**
 * This is another utility class that will get you some info about the server
 * where your PHP scripts are running.
 *
 */
class Server
{

    /**
     * Get server load ... if linux, returns all three averages, if windows, returns
     * average load for all CPU cores
     *
     * @return string
     * @throws Exception
     */
	public static function getServerLoad(): string
    {
        if (function_exists('sys_getloadavg')) {
            $a = sys_getloadavg();
            foreach ($a as $k => $v) {
                $a[$k] = round($v, 2);
            }
            return implode(', ', $a);
        } else {
            $os = strtolower(PHP_OS);
            if (!str_contains($os, 'win')) {
                if (@file_exists('/proc/loadavg') && @is_readable('/proc/loadavg')) {
                    $load = file_get_contents('/proc/loadavg');
                    $load = explode(' ', $load);
                    return implode(',', $load);
                } else if (function_exists('shell_exec')) {
                    $load = @shell_exec('uptime');
                    $load = explode('load average' . (PHP_OS == 'Darwin' ? 's' : '') . ':', $load);
                    return implode(',', $load);
                    //return $load[count($load)-1];
                } else {
                    throw new Exception('Unable to get server load');
                }
            }
        }

        throw new Exception('Unable to get server load');
    }

    /**
     * Get the server's "signature" with all useful debug data
     *
     * @return array
     * @throws Convert\Exception
     * @throws Exception
     */
	public static function signatureArray(): array
    {
        $numberOfIncludedFiles = count(get_included_files());
        $serverIP = static::ip();
        $domain = Application::getDomain();

        $signature = [];

        // add the server IP and domain
        $signature[] = "server: {$serverIP} ({$domain})";

        if (PHP_SAPI != 'cli') {
            // this is regular HTTP request
            $method = Request::method();
            $url = Application::getCurrentURL();

            // add info about the current request
            $signature[] = "URL: {$method}={$url}";

            // some end user IP and host stuff
            $endUserIp = Request::ip();
            $endUserHost = Request::host() ?? 'no host detected';
            $proxy = '';

            if ($endUserIp == $endUserHost) {
                $endUserHost = 'no host detected';
            }

            if (Request::hasProxy()) {
                $proxySignature = Request::proxySignature();
                $forwardedFor = Request::httpXForwardedFor();
                $proxy = " via {$proxySignature} for {$forwardedFor}";
            }

            $signature[] = "Origin: {$endUserIp} ({$endUserHost}){$proxy}";

            $uas = Request::userAgent() ?? 'no user agent set';
            $signature[] = "UAS: {$uas}";
        } else {
            $cliName = Application::getCliName();
            $cliScript = Application::getCliScriptPath();

            $signature[] = "CLI Name: {$cliName}";
            $signature[] = "CLI Script: {$cliScript}";

            $params = Cli::getParameters();
            if (count($params) > 0) {
                $signature[] = 'CLI Params: ' . print_r($params, true);
            }
        }

        $serverLoad = static::getServerLoad();
        $signature[] = "Server Load: {$serverLoad}";

        $memory = memory_get_usage();
        $peak = memory_get_peak_usage();
        $allocatedMemory = memory_get_peak_usage(true);
        $memoryLimit = ini_get('memory_limit');

		// @phpstan-ignore-next-line
		if (is_bool($memoryLimit) && !$memoryLimit) {
			$memoryLimit = '0B';
		}

        $memoryKb = round($memory / 1024, 2);
        $peakKb = round($peak / 1024, 2);
        $allocatedMemoryKb = round($allocatedMemory / 1024, 2);

        $limit = '';
        $peakSpent = '';

        if ($memoryLimit !== '0B') {
            $limitInt = Convert::stringToBytes($memoryLimit);
            $limit = ", limit: {$memoryLimit}";

            $spent = round($peak / $limitInt * 100, 2);
            $peakSpent = " ({$spent}% of limit)";
        }

        $signature[] = "Memory: current: {$memoryKb}kb, peak: {$peakKb}kb{$peakSpent}, allocated: {$allocatedMemoryKb}kb{$limit}";
        $signature[] = "No. of included files: {$numberOfIncludedFiles}";

        return $signature;
    }

    /**
     * @return string
     * @throws Convert\Exception
     * @throws Exception
     */
	public static function signature(): string
    {
        return implode("\n", static::signatureArray());
    }

    /**
     * Get the IP address of server (will use SERVER_ADDR)
     *
     * @return string
     */
	public static function ip(): string
    {
        return $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
    }

}
