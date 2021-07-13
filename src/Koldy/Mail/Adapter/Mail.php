<?php declare(strict_types=1);

namespace Koldy\Mail\Adapter;

use Koldy\Http\Mime;
use Koldy\Mail\Exception;
use Koldy\Util;

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
	 * @throws \Koldy\Exception
	 * @throws \Koldy\Http\Exception
	 */
	public function send(): void
	{
		$charset = $this->config['charset'] ?? 'utf-8';
		$this->setHeader('Content-Type', $this->isHTML ? "text/html; charset={$charset}" : "text/plain; charset={$charset}");

		if ($this->fromEmail !== null) {
			if ($this->fromName !== null) {
				$from = "{$this->fromName} <{$this->fromEmail}>";
			} else {
				$from = $this->fromEmail;
			}

			$this->setHeader('From', $from);
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

		$to = implode(', ', $to);

		if (count($cc) > 0) {
			$this->setHeader('Cc', implode(', ', $cc));
		}

		if (count($bcc) > 0) {
			$this->setHeader('Bcc', implode(', ', $bcc));
		}

		if ($this->replyTo !== null) {
			$this->setHeader('Reply-To', $this->replyTo);
		}

		$body = $this->body;

		$attachmentsCount = count($this->attachedFiles);

		if ($attachmentsCount > 0) {
			$boundary = 'b1_' . md5(Util::randomString(10)); // boundary token to be used
			$originalBody = $body;
			$body = '';

			$this->setHeader('MIME-Version', '1.0');
			$this->setHeader('Content-Type', "multipart/mixed; boundary=\"{$boundary}\"");

			if ($this->isHTML) {
				// there are two cases if it's html mail:
				// 1) there is alternative plain text
				// 2) there's no alternative plain text

				if ($this->alternativeText !== null) {
					$this->setHeader('Content-Transfer-Encoding', '8bit');

					// 1) if there's alternative text, use it as text/plain
					$alternativeBoundary = 'b2_' . md5(Util::randomString(10)); // alternative boundary token to be used

					$body .= "This is a multi-part message in MIME format.\r\n";

					$body .= "--{$boundary}\r\n";
					$body .= "Content-Type: multipart/alternative; boundary=\"{$alternativeBoundary}\"\r\n\r\n";

					$body .= "--{$alternativeBoundary}\r\n";
					$body .= "Content-Type: text/plain; charset={$charset}\r\n";
					$body .= wordwrap($this->alternativeText, 70, "\r\n");

					$body .= "\r\n\r\n--{$alternativeBoundary}\r\n";
					$body .= "Content-Type: text/html; charset={$charset}\r\n\r\n";
					$body .= $originalBody;

					$body .= "\r\n\r\n--{$alternativeBoundary}--\r\n\r\n";
				} else {
					// 2) if there's no alternative text, use it as text/plain
					$body .= "--{$boundary}\r\n";
					$body .= "Content-Type: text/html; charset=\"{$charset}\"\r\n";
					$body .= "Content-Transfer-Encoding: base64\r\n\r\n";
					$body .= chunk_split(base64_encode($originalBody));
				}
			} else {
				// if it's not html message, then use original body as plain text only
				$body .= "--{$boundary}\r\n";
				$body .= "Content-Type: text/plain; charset=\"{$charset}\"\r\n";
				$body .= "Content-Transfer-Encoding: base64\r\n\r\n";
				$body .= chunk_split(base64_encode($originalBody));
			}

			$attachmentIds = [];

			foreach ($this->attachedFiles as $file) {
				$fullFilePath = $file['fullFilePath'];
				$extension = $file['extension'];
				$attachedAsName = $file['attachedAsName'];
				$fileSize = filesize($fullFilePath);
				$fileType = Mime::getMimeByExtension($extension);

				//read file
				$fp = fopen($fullFilePath, 'r');

				if ($fp === false) {
					// can't open file, let's try to detect the reason of not opening

					if (is_file($fullFilePath)) {
						throw new Exception("Can not attach file to email because file can not be opened; path={$fullFilePath}");
					} else {
						throw new Exception("Can not attach file to email because file is not found on {$fullFilePath}");
					}
				}

				$fileContent = fread($fp, $fileSize);
				fclose($fp);
				$encodedFileContent = chunk_split(base64_encode($fileContent)); // (RFC 2045)

				$cnt = 0;

				do {
					$attachmentId = rand(1000, 99999);
				} while (array_key_exists($attachmentId, $attachmentIds) && $cnt++ < 200);

				if ($cnt === 200) {
					throw new Exception('Unable to generate unused file attachment ID not even after 200 attempts');
				}

				$attachmentIds[$attachmentId] = true;

				$body .= "--{$boundary}\r\n";
				$body .= "Content-Type: {$fileType}; name={$attachedAsName}\r\n";
				$body .= "Content-Disposition: attachment; filename={$attachedAsName}\r\n";
				$body .= "Content-Transfer-Encoding: base64\r\n";
				$body .= "X-Attachment-Id: {$attachmentId}\r\n\r\n";
				$body .= $encodedFileContent;
			}

			$body .= "\r\n\r\n--{$boundary}--\r\n\r\n";
		}

		if (mail($to, $this->subject, $body, implode("\r\n", $this->getHeadersList())) === false) {
			throw new Exception("Unable to send e-mail using sendmail to={$to}");
		}
	}

}
