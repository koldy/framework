<?php declare(strict_types = 1);

namespace Koldy\Mail\Adapter;

/**
 * Class CommonMailAdapter
 * @package Koldy\Mail\Adapter
 */
abstract class CommonMailAdapter extends AbstractMailAdapter
{

    /**
     * The array of recipients
     *
     * @var array
     */
    protected $to = [];

    /**
     * The array of CC recipients
     *
     * @var array
     */
    protected $cc = [];

    /**
     * The array of BCC recipients
     *
     * @var array
     */
    protected $bcc = [];

    /**
     * @var string
     */
    protected $fromEmail = null;

    /**
     * @var string
     */
    protected $fromName = null;

    /**
     * @var string
     */
    protected $replyTo = null;

    /**
     * @var string
     */
    protected $subject = null;

    /**
     * @var string
     */
    protected $body = null;

    /**
     * @var string
     */
    protected $alternativeText = null;

    /**
     * @var bool
     */
    protected $isHTML = false;

    /**
     * @var array
     */
    protected $attachedFiles = [];

    /**
     * Set email's "from"
     *
     * @param string $email
     * @param string $name [optional]
     *
     * @return $this
     */
    public function from(string $email, string $name = null)
    {
        $this->fromEmail = $email;
        $this->fromName = $name;

        return $this;
    }

    /**
     * Set email's "Reply To" option
     *
     * @param string $email
     * @param string $name [optional]
     *
     * @return $this
     */
    public function replyTo(string $email, string $name = null)
    {
        $this->replyTo = $this->getAddressValue($email, $name);
        return $this;
    }

    /**
     * Set email's "to"
     *
     * @param string $email
     * @param string $name [optional]
     *
     * @return $this
     */
    public function to(string $email, string $name = null)
    {
        $this->to[] = [
          'email' => $email,
          'name' => $name
        ];

        return $this;
    }

    /**
     * Set email's "cc"
     *
     * @param string $email
     * @param string $name [optional]
     *
     * @return $this
     */
    public function cc(string $email, string $name = null)
    {
        $this->cc[] = [
          'email' => $email,
          'name' => $name
        ];

        return $this;
    }

    /**
     * Set email's "bcc"
     *
     * @param string $email
     * @param string $name [optional]
     *
     * @return $this
     */
    public function bcc(string $email, string $name = null)
    {
        $this->bcc[] = [
          'email' => $email,
          'name' => $name
        ];

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
        $this->subject = $subject;
        return $this;
    }

    /**
     * Sets the e-mail's body in HTML format. If you want to send plain text only, please use plain() method.
     *
     * @param string $body
     * @param boolean $isHTML
     * @param string $alternativeText
     *
     * @return $this
     */
    public function body(string $body, bool $isHTML = false, string $alternativeText = null)
    {
        $this->body = is_object($body) && method_exists($body, '__toString') ? $body->__toString() : $body;
        $this->isHTML = $isHTML;
        $this->alternativeText = $alternativeText;
        return $this;
    }

    /**
     * Attach file to e-mail
     *
     * @param string $filePath
     * @param string $name [optional]
     *
     * @return $this
     */
    public function attachFile(string $filePath, string $name = null)
    {
        $this->attachedFiles[] = [
          'path' => $filePath,
          'name' => $name
        ];

        return $this;
    }

}
