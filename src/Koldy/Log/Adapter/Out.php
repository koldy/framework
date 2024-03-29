<?php declare(strict_types=1);

namespace Koldy\Log\Adapter;

use Closure;
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
     * @var Closure|null
     */
    protected Closure|null $getMessageFunction = null;

    private const FN_CONFIG_KEY = 'get_message_fn';

	/**
	 * Construct the handler to log to files. The config array will be check
	 * because all configs are strict
	 *
	 * @param array $config
	 *
	 * @throws Exception
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Convert\Exception
	 * @throws \Koldy\Exception
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

        parent::__construct($config);

        if (isset($config['dump'])) {
            $self = $this;

            register_shutdown_function(function () use ($self) {
                $self->dump();
            });
        }
    }

	/**
	 * Actually print message out
	 *
	 * @param Message $message
	 *
	 * @throws \Koldy\Exception
	 */
    public function logMessage(Message $message): void
    {
        if (in_array($message->getLevel(), $this->config['log'])) {
            if ($this->getMessageFunction !== null) {
                $line = call_user_func($this->getMessageFunction, $message);
            } else {
                $time = $message->getTime()->format('y-m-d H:i:s.v');
                $level = strtoupper($message->getLevel());
                $space = str_repeat(' ', 10 - strlen($level));
                $who = $message->getWho() ?? Log::getWho();
                $line = "{$time} {$level}{$space}{$who}\t{$message->getMessage()}\n";
            }

            if (is_string($line)) {
                print $line;
            }
        }
    }

}
