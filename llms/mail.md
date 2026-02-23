# Koldy PHP Framework — Mail

`Koldy\Mail` is a static facade for sending emails through pluggable adapters.

## Configuration

```php
// configs/mail.php
return [
    'default' => [
        'enabled' => true,
        'adapter_class' => \Koldy\Mail\Adapter\Mail::class,
        'options' => [
            'from' => 'noreply@example.com',
            'from_name' => 'My App'
        ]
    ],
    'file' => [
        'enabled' => true,
        'adapter_class' => \Koldy\Mail\Adapter\File::class,
        'options' => [
            'path' => null  // null = auto (storage_path/mail/)
        ]
    ]
];
```

## Sending Email

```php
use Koldy\Mail;

$mail = Mail::create();            // use default adapter
$mail = Mail::create('file');      // use named adapter

$mail->from('sender@example.com', 'Sender Name');
$mail->to('recipient@example.com', 'Recipient Name');
$mail->cc('cc@example.com');
$mail->bcc('bcc@example.com');
$mail->subject('Hello!');
$mail->body('Plain text body');
$mail->html('<h1>Hello!</h1>');
$mail->send();
```

## Status Check

```php
Mail::isEnabled('default');    // bool — is adapter enabled
```

## Built-in Adapters

| Adapter | Class | Description |
|---------|-------|-------------|
| Mail | `Mail\Adapter\Mail` | PHP's built-in `mail()` function |
| File | `Mail\Adapter\File` | Writes emails to files instead of sending. Useful for development/testing. |
| Simulate | `Mail\Adapter\Simulate` | No-op adapter. Simulates sending without any action. |

Koldy framework supports only native PHP mail() function for sending emails. If you need more advanced features, you should use companion package [koldy/phpmailer](https://github.com/koldy/phpmailer) which integrates PHPMailer so you get SMTP support.

You may use external provider's API as well, but then you should create your own adapter: simply extend `Mail\Adapter\AbstractMailAdapter`, implement required methods, and register it in config/mail.php.

## PHPMailer Integration

For advanced email features (SMTP, attachments, etc.), use the companion package:

```bash
composer require koldy/phpmailer
```

See [koldy/phpmailer](https://github.com/koldy/phpmailer) for integration details.
