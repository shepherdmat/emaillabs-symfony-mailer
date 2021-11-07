Emaillabs Symfony Mailer
================

Provides [Emaillabs](https://emaillabs.io) integration for [Symfony Mailer](https://symfony.com/doc/current/mailer.html).

## Installation

The preferred method of installation is via [Composer][]. Run the following
command to install the package and add it as a requirement to your project's
`composer.json`:

```bash
composer require shepherdmat/emaillabs-symfony-mailer
```

## Usage ##

Send email using standard [SymfonyHttpClient](https://symfony.com/doc/current/http_client.html) as http interface.

```php
// require_once __DIR__ . './vendor/autoload.php';

use Shepherdmat\Mailer\Emaillabs\Transport\EmaillabsApiTransport;
use Shepherdmat\Mailer\Emaillabs\Transport\EmaillabsTransportFactory;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

// Your active host account (https://panel.emaillabs.net.pl/pl/smtp).
$host = 'YOUR_ACCOUNT.smtp';

// Your App Key (https://panel.emaillabs.net.pl/pl/site/api).
$appKey = 'XXXXXXX';

// Your Secret Key (https://panel.emaillabs.net.pl/pl/site/api).
$appSecret = 'YYYYYYY';

$transportFactory = new EmaillabsTransportFactory(null, HttpClient::create());
$dsn = new Dsn(EmaillabsTransportFactory::SCHEME, $host, $appKey, $appSecret);

$mailer = new Mailer($transportFactory->create($dsn));

$message = (new Email())
    ->from('foo@bar.dev')
    ->to('bar@foo.dev')
    ->subject('Message title')
    ->html('<b>HTML message content</b>')
    ->text('Text message content')
    // Attachments are handled by default.
    ->attachFromPath('./path/to/attachment')
    ->embedFromPath('./path/to/attachment', 'embed_tag');
    

// If you want to pass some api parameters, use dedicated headers.
// (https://dev.emaillabs.io/#api-Send-new_sendmail)
$message->getHeaders()
    // Comma-separated list of tags.
    ->addTextHeader(EmaillabsApiTransport::HEADER_TAGS, 'tag1,tag2,tag3')
    // Custom template ID.
    ->addTextHeader(EmaillabsApiTransport::HEADER_TEMPLATE, 'template_id')
    // Custom return path.
    ->addTextHeader(EmaillabsApiTransport::HEADER_RETURN_PATH, 'return_path');

$mailer->send($message);
``` 

## License

This bundle is under the MIT license.  
For the whole copyright, see the [LICENSE](LICENSE) file distributed with this source code.