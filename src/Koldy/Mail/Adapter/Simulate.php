<?php declare(strict_types=1);

namespace Koldy\Mail\Adapter;

use Koldy\Application;
use Koldy\Exception;
use Koldy\Log;

/**
 * This is mail adapter class that won't do anything. Instead of actually sending the mail, this class will dump email data into log [INFO].
 *
 * @link http://koldy.net/docs/mail/simulate
 */
class Simulate extends CommonMailAdapter
{

    /**
     * @throws Exception
     */
    public function send(): void
    {
        $from = ($this->fromName !== null) ? "{$this->fromName} <{$this->fromEmail}>" : $this->fromEmail;

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
            $cc = ' CC=' . implode(', ', $cc);
        }

        if (count($bcc) > 0) {
            $bcc = ' BCC=' . implode(', ', $bcc);
        }

        $replyTo = '';
        if ($this->replyTo != null) {
            $replyTo = ' replyTo=' . $this->replyTo;
        }

        Log::info("E-mail [SIMULATED] is sent FROM={$from}{$replyTo} TO={$to}{$cc}{$bcc} with subject \"{$this->subject}\" and content length: " . mb_strlen($this->body, Application::getEncoding()));
    }

}
