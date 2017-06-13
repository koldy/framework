<?php declare(strict_types = 1);

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
            if (strpos($os, 'win') === false) {
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
            } else if (class_exists('COM')) {
                $wmi = new \COM("WinMgmts:\\\\.");
                $CPUs = $wmi->InstancesOf('Win32_Processor');

                $cpuLoad = 0;
                $i = 0;

                while ($cpu = $CPUs->Next()) {
                    $cpuLoad += $cpu->LoadPercentage;
                    $i++;
                }

                $cpuLoad = round($cpuLoad / $i, 2);
                return $cpuLoad . '%';
            }
        }

        throw new Exception('Unable to get server load');
    }

    /**
     * Get the server's "signature" in this moment with all useful debug data
     *
     * @return string
     */
    public static function signature(): string
    {
        $numberOfIncludedFiles = count(get_included_files());
        $signature = sprintf("server: %s (%s)\n", static::ip(), Application::getDomain());

        if (PHP_SAPI != 'cli') {
            $signature .= 'URI: ' . $_SERVER['REQUEST_METHOD'] . '=' . Application::getDomain() . Application::getUri() . "\n";
            $signature .= sprintf("User IP: %s (%s)%s", Request::ip(), Request::host(),
              (Request::hasProxy() ? sprintf(" via %s for %s\n", Request::proxySignature(), Request::httpXForwardedFor()) : "\n"));
            $signature .= sprintf("UAS: %s\n", (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'no user agent set'));
        } else {
            $signature .= 'CLI Name: ' . Application::getCliName() . "\n";
            $signature .= 'CLI Script: ' . Application::getCliScriptPath() . "\n";

            $params = Cli::getParameters();
            if (count($params) > 0) {
                $signature .= 'CLI Params: ' . print_r($params, true) . "\n";
            }
        }

        $signature .= sprintf("Server load: %s\n", static::getServerLoad());

        $peak = memory_get_peak_usage(true);
        $memoryLimit = ini_get('memory_limit');

        $signature .= sprintf("Memory: %s; peak: %s; limit: %s; spent: %s%%\n", Convert::bytesToString(memory_get_usage(true)), Convert::bytesToString($peak), $memoryLimit,
          ($memoryLimit !== false && $memoryLimit > 0 ? round($peak * 100 / Convert::stringToBytes($memoryLimit), 2) : 'null'));
        $signature .= sprintf("No. of included files: %d\n", $numberOfIncludedFiles);

        return $signature;
    }

    /**
     * Get the IP address of server (will use SERVER_ADDR)
     *
     * @return string
     */
    public static function ip(): string
    {
        return isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '127.0.0.1';
    }

}
