<?php declare(strict_types = 1);

namespace Koldy\Log\Adapter;

use Koldy\Application;
use Koldy\Log\Exception;
use Koldy\Log;
use Koldy\Log\Message;

/**
 * This log writer will print all messages to console. This writer is made to
 * be used in CLI environment.
 *
 * @link http://koldy.net/docs/log/out
 *
 */
class Out extends AbstractLogAdapter
{

    /**
     * Get message function handler
     *
     * @var \Closure
     */
    protected $getMessageFunction = null;
    
    private const FN_CONFIG_KEY = 'get_message_fn';

    /**
     * Construct the handler to log to files. The config array will be check
     * because all configs are strict
     *
     * @param array $config
     *
     * @throws Exception
     */
    public function __construct(array $config)
    {
        if (!isset($config['log']) || !is_array($config['log'])) {
            throw new Exception('You must define \'log\' levels in file log adapter config options at least with empty array');
        }

        if (isset($config[self::FN_CONFIG_KEY])) {
            if ($config[self::FN_CONFIG_KEY] instanceof \Closure) {
                $this->getMessageFunction = $config[self::FN_CONFIG_KEY];
            } else {

                if (is_object($config[self::FN_CONFIG_KEY])) {
                    $got = get_class($config[self::FN_CONFIG_KEY]);
                } else {
                    $got = gettype($config[self::FN_CONFIG_KEY]);
                }

                throw new Exception('Invalid get_message_fn type; expected \Closure object, got: ' . $got);
            }
        }

        if (!isset($config['dump'])) {
            $config['dump'] = [];
        } else {
            $self = $this;
            register_shutdown_function(function () use ($self) {
                $dump = $self->config['dump'];

                // 'speed', 'included_files', 'include_path', 'whitespace'

                if (in_array('speed', $dump)) {
                    $method = isset($_SERVER['REQUEST_METHOD']) ? ($_SERVER['REQUEST_METHOD'] . '=' . Application::getCurrentURL()) : ('CLI=' . Application::getCliName());

                    $executedIn = Application::getRequestExecutionTime();
                    $self->logMessage(new Message($method . ' LOADED IN ' . $executedIn . 'ms, ' . count(get_included_files()) . ' files', 'notice'));
                }

                if (in_array('included_files', $dump)) {
                    $self->logMessage(new Message(print_r(get_included_files(), true), 'notice'));
                }

                if (in_array('include_path', $dump)) {
                    $self->logMessage(new Message(print_r(explode(':', get_include_path()), true), 'notice'));
                }

                if (in_array('whitespace', $dump)) {
                    $self->logMessage(new Message("----------\n\n\n", 'notice'));
                }
            });
        }

        parent::__construct($config);
    }

    /**
     * Actually print message out
     *
     * @param Message $message
     */
    public function logMessage(Message $message): void
    {
        if (in_array($message->getLevel(), $this->config['log'])) {
            if ($this->getMessageFunction !== null) {
                $line = call_user_func($this->getMessageFunction, $message);
            } else {
                $who = $message->getWho() ?? Log::getWho();
                $line = "{$message->getTime()->format('Y-m-d H:i:sO')}\t{$message->getLevel()}\t{$who}\t{$message->getMessage()}\n";
            }

            if (is_string($line)) {
                print $line;
            }
        }
    }

}
