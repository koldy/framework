<?php declare(strict_types = 1);

namespace Koldy\Log\Adapter;

use Koldy\Config\Exception as ConfigException;
use Koldy\Http\{
  Request as HttpRequest, Exception as HttpException
};
use Koldy\Log;

/**
 * This log adapter will simply send logged to some external URL. It's good for sending log messages to Slack or Sentry.
 *
 * @link http://koldy.net/docs/log/http
 */
class Http extends AbstractLogAdapter
{

    /**
     * The flag we're already sending an e-mail, to prevent recursion
     *
     * @var boolean
     */
    private $sending = false;

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

        if (isset($config['prepare_request']) && !is_callable($config['prepare_request'])) {
            throw new ConfigException('prepare_request in HTTP writer options is not callable');
        }

        if (!isset($config['max_messages'])) {
            $config['max_messages'] = 100;
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
     * @param string $level
     * @param string $message
     */
    protected function logMessage(string $level, string $message): void
    {
        if ($this->sending) {
            return;
        }

        if (in_array($level, $this->config['log'])) {

            $data = array(
              'time' => gmdate('Y-m-d H:i:sO'),
              'level' => $level,
              'who' => Log::getWho(),
              'message' => $message
            );

            $message = implode("\t", array_values($data));

            if ($this->config['send_immediately']) {
                $this->sendRequest($data);
            } else {
                $this->appendMessage($message);
            }
        }
    }

    /**
     * Send HTTP request if system detected that request should be sent
     *
     * @param null|array $message
     */
    protected function sendRequest(array $message = null)
    {
        if ($message === null) {
            $messages = implode("\n", $this->messages);
        } else {
            $messages = $message;
        }

        $this->sending = true;
        /** @var \Closure $prepareRequest */
        $prepareRequest = $this->config['prepare_request'];

        /** @var HttpRequest $request */
        $request = $prepareRequest($messages);
        if (!($request instanceof HttpRequest)) {
            Log::emergency('Log/HTTP adapter prepare_request didn\'t return instance of \Koldy\Http\Request');
            $this->sending = false;
        } else {
            try {
                $request->exec();
                $this->messages = [];
            } catch (HttpException $e) {
                Log::alert('Can not send log by e-mail', $e);
            }
        }
    }

    public function shutdown(): void
    {
        if (count($this->messages) > 0) {
            $this->sendRequest();
        }
    }

}
