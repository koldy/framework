<?php declare(strict_types=1);

namespace Koldy\Mail\Adapter;

use DateTime;
use Koldy\Application;
use Koldy\Filesystem\Directory;
use Koldy\Mail\Exception;

/**
 * This mail adapter class will create nice file where all email details will be printed
 *
 * @link http://koldy.net/docs/mail/file
 * @link http://php.net/manual/en/function.mail.php
 */
class File extends CommonMailAdapter
{

    /**
     * @throws Exception
     */
    public function send(): void
    {
        $content = [];

        if ($this->hasHeader('From')) {
            $content[] = 'From: ' . $this->getHeader('From');
            $this->removeHeader('From');
        }

        $to = $cc = $bcc = [];

        if (count($this->to) == 0) {
            throw new Exception('There\'s no recipients to send email to');
        }

        foreach ($this->to as $address) {
            $to[] = $this->getAddressValue($address['email'], $address['name']);
        }

        foreach ($this->cc as $address) {
            $cc[] = $this->getAddressValue($address['email'], $address['name']);
        }

        foreach ($this->bcc as $address) {
            $bcc[] = $this->getAddressValue($address['email'], $address['name']);
        }

        $content[] = 'To: ' . implode(', ', $to);

        if (count($cc) > 0) {
            $content[] = 'Cc: ' . implode(', ', $cc);
        }

        if (count($bcc) > 0) {
            $content[] = 'Bcc: ' . implode(', ', $bcc);
        }

        if ($this->hasHeader('Reply-To')) {
            $content[] = 'Reply-To: ' . $this->getHeader('Reply-To');
            $this->removeHeader('Reply-To');
        }

        $content[] = 'Subject: ' . $this->subject;

        $charset = $this->config['charset'] ?? 'utf-8';
        $contentType = ($this->isHTML) ? ('text/html; charset=' . $charset) : ('text/plain; charset=' . $charset);
        $this->setHeader('Content-type', $contentType);

        $content = implode("\n", $content) . "\n" . str_repeat('=', 80) . "\n";

        $content .= $this->body;

        if ($this->alternativeText != null) {
            $content .= "\n" . str_repeat('=', 80) . "\n";
            $content .= $this->alternativeText;
        }

        $now = DateTime::createFromFormat('U.u', (string)microtime(true));
        $time = $now->format('Y-m-d H-i-s.u');

        if (!isset($this->config['location'])) {
            $file = Application::getStoragePath('email' . DS . "{$time}.txt");
        } else {
            $location = $this->config['location'];

            if (substr($location, 0, 8) == 'storage:') {
                $file = Application::getStoragePath(substr($location, 8) . DS . "{$time}.txt");
            } else {
                $file = $location . DS . "{$time}.txt";
                $file = str_replace(DS . DS, DS, $file);
            }
        }

        $directory = dirname($file);
        if (!is_dir($directory)) {
            Directory::mkdir($directory, 0755);
        }

        file_put_contents($file, $content);
    }

}
