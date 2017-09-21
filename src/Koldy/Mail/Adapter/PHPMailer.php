<?php declare(strict_types=1);

namespace Koldy\Mail\Adapter;

use Koldy\Mail\Exception;
use PHPMailer as NativePHPMailer;

/**
 * This is only driver class that uses PHPMailer. You need to set the include path the way that PHP can include it. We recommend that you set that path
 * in config/application.php under additional_include_path. Path defined there must be the path where class.phpmailer.php is located.
 *
 * @link http://koldy.net/docs/mail/phpmailer
 */
class PHPMailer extends AbstractMailAdapter
{

    /**
     * @var NativePHPMailer
     */
    private $mailer = null;

    /**
     * Construct the object
     *
     * @param array $config
     *
     * @throws Exception
     */
    public function __construct(array $config)
    {
        parent::__construct($config);

        /** @var NativePHPMailer mailer */
        $this->mailer = new NativePHPMailer(true);
        $this->mailer->CharSet = isset($config['charset']) ? $config['charset'] : 'UTF-8';
        $this->mailer->Host = $config['host'];
        $this->mailer->Port = $config['port'];

        if (isset($config['username']) && $config['username'] !== null) {
            $this->mailer->Username = $config['username'];
        }

        if (isset($config['password']) && $config['password'] !== null) {
            $this->mailer->Password = $config['password'];
        }

        switch ($config['type']) {
            default:
            case 'smtp':
                $this->mailer->isSMTP();

                if (isset($config['username']) && $config['username'] !== null && isset($config['password']) && $config['password'] !== null) {
                    $this->mailer->SMTPAuth = true;
                }

                break;

            case 'mail':
                $this->mailer->isMail();
                break;
        }
    }

    /**
     * Set email's "from"
     *
     * @param string $email
     * @param string|null $name
     *
     * @return $this
     */
    public function from(string $email, string $name = null)
    {
        $this->mailer->setFrom($email, $name ?? '');
        return $this;
    }

    /**
     * Set email's "Reply To" option
     *
     * @param string $email
     * @param string|null $name
     *
     * @return $this
     */
    public function replyTo(string $email, string $name = null)
    {
        $this->mailer->addReplyTo($email, $name ?? '');
        return $this;
    }

    /**
     * Set email's "to"
     *
     * @param string $email
     * @param string|null $name
     *
     * @return $this
     */
    public function to(string $email, string $name = null)
    {
        $this->mailer->addAddress($email, $name ?? '');
        return $this;
    }

    /**
     * Send mail carbon copy
     *
     * @param string $email
     * @param string|null $name
     *
     * @return $this
     * @link http://koldy.net/docs/mail#example
     */
    public function cc(string $email, string $name = null)
    {
        $this->mailer->addCC($email, $name ?? '');
        return $this;
    }

    /**
     * Send mail blind carbon copy
     *
     * @param string $email
     * @param string|null $name
     *
     * @return $this
     * @link http://koldy.net/docs/mail#example
     */
    public function bcc(string $email, string $name = null)
    {
        $this->mailer->addBCC($email, $name ?? '');
        return $this;
    }

    /**
     * Set email's subject
     *
     * @param string $subject
     *
     * @return $this
     */
    public function subject(string $subject)
    {
        $this->mailer->Subject = $subject;
        return $this;
    }

    /**
     * @param string $body
     * @param bool $isHTML
     * @param string|null $alternativeText
     *
     * @return $this
     */
    public function body(string $body, bool $isHTML = false, string $alternativeText = null)
    {
        $this->mailer->Body = $body;

        if ($isHTML) {
            $this->mailer->isHTML();
        }

        if ($alternativeText !== null) {
            $this->mailer->AltBody = $alternativeText;
        }

        return $this;
    }

    /**
     * @param string $filePath
     * @param string $name
     *
     * @return $this
     */
    public function attachFile(string $filePath, string $name = null)
    {
        $this->mailer->addAttachment($filePath, $name ?? '');
        return $this;
    }

    /**
     * @throws Exception
     */
    public function send(): void
    {
        try {

            if (!$this->mailer->send()) {
                throw new Exception($this->mailer->ErrorInfo);
            }

        } catch (\phpmailerException $e) {
            throw new Exception($e->getMessage(), (int)$e->getCode(), $e);

        } catch (\Throwable $e) {
            throw new Exception($e->getMessage(), (int)$e->getCode(), $e);

        }
    }

    /**
     * Get the PHP mailer instance for fine tuning
     *
     * @return NativePHPMailer
     */
    public function getPHPMailer(): NativePHPMailer
    {
        return $this->mailer;
    }

}
