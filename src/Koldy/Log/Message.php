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
    protected $messages = [];

    /**
     * @var string|null
     */
    protected $level;

    /**
     * @var DateTime
     */
    protected $time;

    /**
     * @var string
     */
    protected $who = null;

    private const TYPE_PHP = 'php_error';
    private const DEFAULT_TIME_FORMAT = 'Y-m-d\TH:i:s.uO';

    /**
     * Message constructor.
     *
     * @param string $level
     * @param string|null $message
     */
    public function __construct(string $level, string $message = null)
    {
        $this->level = $level;
        $this->time = DateTime::createFromFormat('U.u', sprintf('%.f', microtime(true)));

        if ($message !== null) {
            $this->messages[] = $message;
        }
    }

    /**
     * Get or set the time of message
     *
     * @return DateTime
     */
    public function getTime(): DateTime
    {
        return $this->time;
    }

    /**
     * @return string
     */
    public function getTimeFormatted(): string
    {
        return $this->getTime()->format(self::DEFAULT_TIME_FORMAT);
    }

    /**
     * Manually set the time
     *
     * @param DateTime $time
     *
     * @return Message
     */
    public function setTime(DateTime $time): self
    {
        $this->time = $time;
        return $this;
    }

    /**
     * @param string $message
     *
     * @return Message
     */
    public function addMessagePart(string $message): self
    {
        $this->messages[] = $message;
        return $this;
    }

    /**
     * Add standard PHP error message
     *
     * @param string $message
     * @param string $file
     * @param int $number
     * @param int $line
     *
     * @return Message
     */
    public function addPHPErrorMessage(string $message, string $file, int $number, int $line): self
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
     * @param array $messages
     *
     * @return Message
     */
    public function setMessages(array $messages): self
    {
        $this->messages = $messages;
        return $this;
    }

    /**
     * Get all messages
     *
     * @return array
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get the actual message only, depending on data we have
     *
     * @param string $delimiter
     *
     * @return string
     */
    public function getMessage(string $delimiter = ' '): string
    {
        $messages = $this->getMessages();
        $return = [];

        foreach ($messages as $part) {
            if (is_array($part)) {

                $type = $part['type'] ?? '';
                if (!in_array($type, [self::TYPE_PHP])) {
                    $return[] = print_r($part, true);
                } else {
                    $return[] = "PHP [{$part['number']}] {$part['message']} in file {$part['file']}:{$part['line']}";
                }

            } else if (is_object($part) && $part instanceof Throwable) {
                if (KOLDY_CLI) {
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
     * Set "who" on this log message
     *
     * @param string $who
     *
     * @return Message
     */
    public function setWho(string $who): self
    {
        $this->who = $who;
        return $this;
    }

    /**
     * Get "who" on this log message
     *
     * @return string
     */
    public function getWho(): ?string
    {
        return $this->who;
    }

    /**
     * @param string $level
     *
     * @return Message
     */
    public function setLevel(string $level): self
    {
        $this->level = $level;
        return $this;
    }

    /**
     * @return string
     */
    public function getLevel(): string
    {
        return $this->level;
    }

    /**
     * @return string
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
     */
    public function __toString(): string
    {
        return $this->getDefaultLine();
    }

}
