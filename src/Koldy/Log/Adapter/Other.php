<?php declare(strict_types = 1);

namespace Koldy\Log\Adapter;

use Closure;
use Koldy\Config\Exception as ConfigException;
use Koldy\Log\Message;

/**
 * This log adapter will simply collect log messages or it'll execute function immediately. It's all up to you.
 *
 * @link http://koldy.net/docs/log/other
 */
class Other extends AbstractLogAdapter
{

    /**
     * The array of last X messages (by default, the last 100 messages)
     *
     * @var array
     */
    protected $messages = [];

    /**
     * Construct the HTTP log adapter
     *
     * @param array $config
     *
     * @throws ConfigException
     */
    public function __construct(array $config)
    {
        if (!isset($config['send_immediately'])) {
            $config['send_immediately'] = false;
        }

        if (isset($config['exec']) && !is_callable($config['exec'])) {
            throw new ConfigException('exec in Other log adapter options is not callable');
        }

        if (!isset($config['max_messages'])) {
            $config['max_messages'] = 200;
        }

        parent::__construct($config);
    }

    /**
     * Append log message to the request's scope
     *
     * @param string $message
     */
    protected function appendMessage(string $message): void
    {
        $this->messages[] = $message;

        if (sizeof($this->messages) > $this->config['max_messages']) {
            array_shift($this->messages);
        }
    }

    /**
     * Actually execute something with our messages
     *
     * @param Message $message
     */
    public function logMessage(Message $message): void
    {
        if (in_array($message->getLevel(), $this->config['log'])) {
            if ($this->config['send_immediately']) {

                call_user_func($this->config['exec'], $message);

            } else {
                $this->messages[] = $message;

                if (count($this->messages) == 1) {
                    // register shutdown on first message in queue
                    $self = $this;
                    register_shutdown_function(function () use ($self) {
                        $fn = $self->getExecFunction();
                        call_user_func($fn, $self->getMessages());
                    });
                }
            }
        }
    }

    /**
     * @return Message[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @return Closure
     */
    public function getExecFunction(): Closure
    {
        return $this->config['exec'];
    }

}
