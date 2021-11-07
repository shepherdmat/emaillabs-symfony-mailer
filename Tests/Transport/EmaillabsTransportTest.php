<?php

declare(strict_types=1);

namespace Shepherdmat\Symfony\Mailer\Emaillabs\Tests\Transport;

use Shepherdmat\Symfony\Mailer\Emaillabs\Transport\EmaillabsApiTransport;
use Shepherdmat\Symfony\Mailer\Emaillabs\Transport\EmaillabsTransportFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\ResponseInterface;

class EmaillabsTransportTest extends TestCase
{
    private const DUMMY_HOST = 'dummy_host';
    private const DUMMY_KEY = 'dummy_key';
    private const DUMMY_SECRET = 'dummy_secret';
    private const DUMMY_ERROR_CODE = 418;
    private const DUMMY_MESSAGE_ID = 'dummy_message_id';

    public function testToString(): void
    {
        $transport = new EmaillabsApiTransport(self::DUMMY_KEY, self::DUMMY_SECRET);
        $transport->setHost(self::DUMMY_HOST);

        $expect = sprintf('%s://%s', EmaillabsTransportFactory::SCHEME, self::DUMMY_HOST);

        $this->assertSame($expect, (string)$transport);
    }

    public function testSend(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame(EmaillabsApiTransport::API_ENDPOINT, $url);

            parse_str($options['body'], $message);

            $this->assertSame('BarFoo', $message['from_name']);
            $this->assertSame('bar@foo.dev', $message['from']);
            $this->assertSame(['foo@bar.dev' => 'FooBar'], $message['to']);
            $this->assertSame('Hello!', $message['subject']);
            $this->assertSame('Hello There!', $message['txt']);

            $responseBody = json_encode([
                'data' => [
                    0 => [self::DUMMY_MESSAGE_ID]
                ]
            ], JSON_THROW_ON_ERROR);

            return new MockResponse($responseBody, ['http_code' => 200]);
        });

        $transport = new EmaillabsApiTransport(self::DUMMY_KEY, self::DUMMY_SECRET, $client);

        $mail = $this->getMail();

        $message = $transport->send($mail);

        $this->assertSame(self::DUMMY_MESSAGE_ID, $message->getMessageId());
    }

    public function testSendThrowsForErrorResponse(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $responseBody = json_encode([
                'message' => 'Dummy error',
                'code' => self::DUMMY_ERROR_CODE
            ], JSON_THROW_ON_ERROR);

            return new MockResponse($responseBody, ['http_code' => self::DUMMY_ERROR_CODE]);
        });

        $transport = new EmaillabsApiTransport(self::DUMMY_KEY, self::DUMMY_SECRET, $client);

        $mail = $this->getMail();

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage(sprintf('Unable to send an email: Dummy error (code %s).', self::DUMMY_ERROR_CODE));

        $transport->send($mail);
    }

    private function getMail(): Email
    {
        return (new Email())
            ->subject('Hello!')
            ->to(new Address('foo@bar.dev', 'FooBar'))
            ->from(new Address('bar@foo.dev', 'BarFoo'))
            ->text('Hello There!');
    }
}