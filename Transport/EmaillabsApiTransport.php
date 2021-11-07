<?php

declare(strict_types=1);

namespace Shepherdmat\Symfony\Mailer\Emaillabs\Transport;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class EmaillabsApiTransport extends AbstractApiTransport
{
    public const API_ENDPOINT = 'https://api.emaillabs.net.pl/api/new_sendmail';
    public const HEADER_TAGS = 'Emaillabs-Tags';
    public const HEADER_TEMPLATE = 'Emaillabs-Template';
    public const HEADER_RETURN_PATH = 'Emaillabs-Return-Path';

    private string $key;
    private string $secret;

    public function __construct(
        string $key,
        string $secret,
        HttpClientInterface $client = null,
        EventDispatcherInterface $dispatcher = null,
        LoggerInterface $logger = null
    ) {
        $this->key = $key;
        $this->secret = $secret;

        parent::__construct($client, $dispatcher, $logger);
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', self::API_ENDPOINT, [
            'auth_basic' => sprintf('%s:%s', $this->key, $this->secret),
            'body' => $this->getPayload($email, $envelope),
        ]);

        $statusCode = $response->getStatusCode();
        $content = $response->toArray(false);

        if (200 !== $statusCode) {
            $errorContent = $content['message'] ?? 'Unknown response error';
            $message = 'Unable to send an email: ' . $errorContent . sprintf(' (code %d).', $statusCode);

            throw new HttpTransportException($message, $response);
        }

        $sentMessage->setMessageId(reset($content['data'][0]));

        return $response;
    }

    public function __toString(): string
    {
        return sprintf('%s://%s', EmaillabsTransportFactory::SCHEME, $this->host);
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $recipients = $this->getRecipients($email, $envelope);

        $payload = [
            'to' => array_reduce($recipients, static function (array $state, Address $address) {
                $state[$address->getAddress()] = $address->getName() ?: $address->getAddress();

                return $state;
            }, []),
            'subject' => $email->getSubject(),
            'smtp_account' => $this->host,
            'html' => (string) $email->getHtmlBody(),
            'txt' => (string) $email->getTextBody(),
            'from' => $envelope->getSender()->getAddress(),
            'files' => $this->prepareAttachments($email),
        ];

        if ($envelope->getSender()->getName()) {
            $payload['from_name'] = $envelope->getSender()->getName();
        }

        if (count($email->getCc())) {
            $payload['cc'] = array_reduce($email->getCc(), static function (array $state, Address $address) {
                $state[$address->getAddress()] = $address->getName() ?: $address->getAddress();

                return $state;
            }, []);
        }

        if (count($email->getBcc())) {
            $payload['bcc'] = array_reduce($email->getBcc(), static function (array $state, Address $address) {
                $state[$address->getAddress()] = $address->getName() ?: $address->getAddress();

                return $state;
            }, []);
        }

        if ($emails = $email->getReplyTo()) {
            $payload['reply_to'] = implode(',', $this->stringifyAddresses($emails));
        }

        $headers = $email->getHeaders();

        if ($headers->get(self::HEADER_TAGS)) {
            $payload['tags'] = explode(',', $headers->get(self::HEADER_TAGS)->getBodyAsString());
            $headers->remove(self::HEADER_TAGS);
        }

        if ($headers->get(self::HEADER_TEMPLATE)) {
            $payload['template_id'] = $headers->get(self::HEADER_TEMPLATE)->getBodyAsString();
            $headers->remove(self::HEADER_TEMPLATE);
        }

        if ($headers->get(self::HEADER_RETURN_PATH)) {
            $payload['return_path'] = $headers->get(self::HEADER_RETURN_PATH)->getBodyAsString();
            $headers->remove(self::HEADER_RETURN_PATH);
        }

        $payload['headers'] = $this->prepareHeaders($email);

        return $payload;
    }

    private function prepareAttachments(Email $email): array
    {
        return array_reduce($email->getAttachments(), static function (array $state, DataPart $attachment) {
            $headers = $attachment->getPreparedHeaders();

            $file = [
                'content' => base64_encode($attachment->getBody()),
                'mime' => $headers->get('Content-Type')->getBody(),
                'name' => $headers->getHeaderParameter('Content-Disposition', 'filename'),
            ];

            if ('inline' === $headers->get('Content-Disposition')->getBody()) {
                $file['inline'] = 1;
            }

            $state[] = $file;

            return $state;
        }, []);
    }

    private function prepareHeaders(Email $email): array
    {
        return array_reduce($email->getHeaders()->toArray(), static function (array $state, string $header) {
            [$key, $val] = explode(': ', $header);
            $state[$key] = $val;

            return $state;
        }, []);
    }
}