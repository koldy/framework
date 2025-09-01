<?php declare(strict_types=1);

namespace Koldy\Mail\Adapter;

use Koldy\Application;
use Koldy\Convert;
use Koldy\Exception;
use Koldy\Log;

/**
 * This is mail adapter class that won't do anything. Instead of actually sending the mail, this class will dump email
 * data into log [INFO].
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

		$log = ['SIMULATION of e-mail that would be sent'];
		$log[] = "From: {$from}";
		$log[] = "To: {$to}";

		if (count($cc)) {
			$log[] = 'CC: ' . implode(', ', $cc);
		}

		if (count($bcc)) {
			$log[] = 'BCC: ' . implode(', ', $bcc);
		}

		$log[] = "Subject: {$this->subject}";

		if ($this->replyTo !== null) {
			$log[] = "Reply-to: {$this->replyTo}";
		}

		$log[] = 'Content length: ' . mb_strlen($this->body, Application::getEncoding());

		$totalFiles = count($this->attachedFiles);

		if ($totalFiles > 0) {
			$totalFileSize = 0;

			foreach ($this->attachedFiles as $key => $file) {
				$index = $key + 1;
				$log[] = "Attached file {$index}: {$file['name']} ({$file['path']})";
				$totalFileSize += filesize($file['path']);
			}

			$totalSize = Convert::bytesToString($totalFileSize);
			$log[] = "Total size of {$totalFiles} attached file(s): {$totalSize}";
		}

		Log::info(implode("\n", $log));
	}

}
