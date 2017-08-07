<?php declare(strict_types = 1);

namespace Koldy\Mail\Adapter;

use Koldy\Mail\Exception;

/**
 * This mail adapter class will use just internal mail() function to send an e-mail.
 *
 * @link http://koldy.net/docs/mail/mail
 * @link http://php.net/manual/en/function.mail.php
 */
class Mail extends CommonMailAdapter
{

    /**
     * @throws Exception
     */
    public function send(): void
    {
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

        $to = implode(', ', $to);

        if (count($cc) > 0) {
            $this->setHeader('Cc', implode(', ', $cc));
        }

        if (count($bcc) > 0) {
            $this->setHeader('Bcc', implode(', ', $bcc));
        }

        $charset = $this->config['charset'] ?? 'utf-8';
        $this->setHeader('Content-Type', $this->isHTML ? "text/html; charset={$charset}" : "text/plain; charset={$charset}");

        $body = $this->body;

        if (count($this->attachedFiles) > 0) {
            throw new Exception('Unable to use sendmail to send file attachments, feature not implemented yet');
        }

        if (mail($to, $this->subject, $body, implode("\r\n", $this->getHeadersList())) === false) {
            throw new Exception("Unable to send e-mail using sendmail to={$to}");
        }
    }

}
