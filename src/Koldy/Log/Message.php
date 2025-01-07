<?php declare(strict_types=1);

namespace Koldy\Log;

use DateTime;
use Koldy\Application;
use Koldy\Log;
use Throwable;

/**
 * Class which holds the message for logging
 * @package Koldy\Log
 */
class Message
{

    /**
     * Log message
     *
     * @var array
     */
    protected array $messages = [];

    /**
     * @var string
     */
    protected string $level;

    /**
     * @var DateTime
     */
    protected DateTime $time;

    /**
     * @var string|null
     */
    protected string | null $who = null;

    private const TYPE_PHP = 'php_error';
    private const DEFAULT_TIME_FORMAT = 'Y-m-d\TH:i:s.uO';

    /**
     * Message constructor.
     *
     * @param string $level
     * @param string|null $message
     */
    public function __construct(string $level, string|null $message = null)
    {
        $this->level = $level;
        $this->time = DateTime::createFromFormat('U.u', sprintf('%.f', microtime(true)));

        if ($message !== null) {
            $this->messages[] = $message;
        }
    }

    /**
     * Get the time of message
     *
     * @return DateTime
     */
    public function getTime(): DateTime
    {
        return $this->time;
    }

    /**
     * Get the time formatted like 'Y-m-d\TH:i:s.uO'
     *
     * @return string
     */
    public function getTimeFormatted(): string
    {
        return $this->getTime()->format(self::DEFAULT_TIME_FORMAT);
    }

    /**
     * Manually set the time of the log message
     *
     * @param DateTime $time
     *
     * @return Message
     */
    public function setTime(DateTime $time): Message
    {
        $this->time = $time;
        return $this;
    }

    /**
     * Add another message this log message object.
     *
     * @param string $message
     *
     * @return Message
     */
    public function addMessagePart(string $message): Message
    {
        $this->messages[] = $message;
        return $this;
    }

    /**
     * Add standard PHP error message. This is usually for framework's internal use.
     *
     * @param string $message
     * @param string $file
     * @param int $number
     * @param int $line
     *
     * @return Message
     */
    public function addPHPErrorMessage(string $message, string $file, int $number, int $line): Message
    {
        $this->messages[] = [
          'type' => self::TYPE_PHP,
          'message' => $message,
          'file' => $file,
          'number' => $number,
          'line' => $line
        ];

        return $this;
    }

    /**
     * Manually set the array of messages
     *
     * @param array $messages
     *
     * @return Message
     */
    public function setMessages(array $messages): Message
    {
        $this->messages = $messages;
        return $this;
    }

    /**
     * Get the array of log messages. If you pass multiple params to e.g. Log::debug('first', 'second'), then
     * you'll get array of two elements
     *
     * @return array
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get the actual message only, depending on data we have. It's like "get the message line"
     *
     * @param string $delimiter
     *
     * @return string
     * @throws \Koldy\Exception
     */
    public function getMessage(string $delimiter = ' '): string
    {
        $messages = $this->getMessages();
        $return = [];

        foreach ($messages as $part) {
            if (is_array($part)) {

                $type = $part['type'] ?? '';
                if ($type != self::TYPE_PHP) {
                    $return[] = print_r($part, true);
                } else {
                    $return[] = "PHP [{$part['number']}] {$part['message']} in file {$part['file']}:{$part['line']}";
                }

            } else if ($part instanceof Throwable) {
                $isCli = defined('KOLDY_CLI') && KOLDY_CLI === true;

                if ($isCli) {
                    $on = '';
                } else {
                    $on = ' on ' . Application::getCurrentURL();
                }

                $className = get_class($part);
                $return[] = "[{$className}] {$part->getMessage()}{$on} in {$part->getFile()}:{$part->getLine()}\n\n{$part->getTraceAsString()}";

            } else if (is_object($part) && method_exists($part, '__toString')) {
                $return[] = $part->__toString();

            } else if (is_object($part)) {
                $return[] = print_r($part, true);

            } else {
                $return[] = $part;

            }
        }

        return implode($delimiter, $return);
    }

    /**
     * Set "who" on this log message only
     *
     * @param string $who
     *
     * @return Message
     */
    public function setWho(string $who): Message
    {
        $this->who = $who;
        return $this;
    }

    /**
     * Get "who" is on this log message only
     *
     * @return string|null
     */
    public function getWho(): ?string
    {
        return $this->who;
    }

    /**
     * Set the message log level (debug, notice, info, sql, warning, error, alert, emergency, critical)
     *
     * @param string $level
     *
     * @return Message
     */
    public function setLevel(string $level): Message
    {
        $this->level = $level;
        return $this;
    }

    /**
     * Get the message level (debug, notice, info, sql, warning, error, alert, emergency, critical)
     *
     * @return string
     */
    public function getLevel(): string
    {
        return $this->level;
    }

    /**
     * Get the default "message line" that includes time, level, who triggered it and the information
     *
     * @return string
     * @throws \Koldy\Exception
     */
    public function getDefaultLine(): string
    {
        $messages = $this->getMessages();

        if (count($messages) == 0) {
            return '';
        }

        $who = $this->getWho() ?? Log::getWho();
        return "{$this->getTimeFormatted()}\t{$who}\t{$this->getLevel()}\t{$this->getMessage()}";
    }

    /**
     * @return string
     * @throws \Koldy\Exception
     */
    public function __toString(): string
    {
        return $this->getDefaultLine();
    }

}
