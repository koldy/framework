<?php declare(strict_types=1);

namespace Koldy\Log\Adapter;

use Koldy\Application;
use Koldy\Config\Exception as ConfigException;
use Koldy\Log;
use Koldy\Log\Exception;
use Koldy\Log\Message;
use Koldy\Mail;
use Koldy\Mail\Adapter\AbstractMailAdapter;
use Koldy\Server;
use Koldy\Util;

/**
 * This log writer will simply send logged message by e-mail.
 *
 * @link http://koldy.net/docs/log/email
 */
class Email extends AbstractLogAdapter
{

    /**
     * The flag we're already sending an e-mail, to prevent recursion
     *
     * @var boolean
     */
    private $emailing = false;

    /**
     * The array of last X messages (by default, the last 100 messages)
     *
     * @var Message[]
     */
    protected $messages = [];

    private const FN_CONFIG_KEY = 'get_mail_fn';

    /**
     * Construct the DB writer
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

        if (!isset($config['to']) || strlen($config['to']) < 5) {
            throw new ConfigException('Email log sender don\'t have to field');
        }

        if (isset($config[self::FN_CONFIG_KEY]) && !is_callable($config[self::FN_CONFIG_KEY])) {
            throw new ConfigException(self::FN_CONFIG_KEY . ' in DB writer options is not callable');
        }

        if (!isset($config['adapter'])) {
            $config['adapter'] = null;
        }

        parent::__construct($config);
    }

    /**
     * Get the Mail instance ready
     *
     */
    protected function getEmail(): AbstractMailAdapter
    {
        if (isset($this->config[self::FN_CONFIG_KEY])) {
            $mail = call_user_func($this->config[self::FN_CONFIG_KEY], $this->messages);

            if (!($mail instanceof AbstractMailAdapter)) {
                throw new Exception('Function defined in mail config under ' . self::FN_CONFIG_KEY . ' must return instance of \Koldy\Mail\Adapter\AbstractMailAdapter; ' . gettype($mail) . ' given');
            }

            return $mail;
        }

        if (count($this->messages) == 0) {
            throw new Exception('Can not prepare e-mail instance when there\'s no log messages added from before; try not to call getEmail() if messages array is empty');
        }

        $mail = Mail::create($this->config['adapter']);
        $allMessages = [];
        $firstMessage = $this->messages[0];

        foreach ($this->messages as $msg) {
            $allMessages[] = $msg->getDefaultLine();
        }

        $body = implode("\n", $allMessages);

        $level = strtoupper($firstMessage->getLevel());
        $subject = "[{$level}] {$firstMessage->getMessage()}";

        $body .= "\n\n----------\n";
        $body .= Server::signature();
        $subject = Util::truncate($subject, 140);

        $mail->from('alert@' . Application::getDomain(), Application::getDomain())->subject($subject)->body($body);

        $to = $this->config['to'];
        if (!is_array($this->config['to']) && strpos($this->config['to'], ',') !== false) {
            $to = explode(',', $this->config['to']);
        }

        if (is_array($to)) {
            foreach ($to as $toEmail) {
                $mail->to(trim($toEmail));
            }
        } else {
            $mail->to(trim($to));
        }

        return $mail;
    }

    /**
     * Append log message to the request's scope
     *
     * @param Message $message
     */
    protected function appendMessage(Message $message): void
    {
        $this->messages[] = $message;

        if (sizeof($this->messages) > 100) {
            array_shift($this->messages);
        }
    }

    /**
     * @param Message $message
     */
    public function logMessage(Message $message): void
    {
        if ($this->emailing) {
            return;
        }

        if (in_array($message->getLevel(), $this->config['log'])) {
            $this->appendMessage($message);

            if ($this->config['send_immediately']) {
                $this->sendEmail();
            }
        }
    }

    /**
     * Send e-mail report if system detected that e-mail should be sent
     */
    protected function sendEmail(): void
    {
        if (!$this->emailing) {
            $mail = $this->getEmail();

            try {
                $this->emailing = true;
                $mail->send();
                $this->messages = [];
                $this->emailing = false;
            } catch (Mail\Exception $e) {
                Log::alert('Can not send log message(s) with e-mail logger', $e);
            }
        }
    }

    public function shutdown(): void
    {
        if (count($this->messages) > 0) {
            $this->sendEmail();
        }
    }

}
