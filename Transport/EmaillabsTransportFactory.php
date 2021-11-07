<?php

declare(strict_types=1);

namespace Shepherdmat\Symfony\Mailer\Emaillabs\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

final class EmaillabsTransportFactory extends AbstractTransportFactory
{
    public const SCHEME = 'emaillabs';

    protected function getSupportedSchemes(): array
    {
        return [self::SCHEME];
    }

    public function create(Dsn $dsn): TransportInterface
    {
        $user = $this->getUser($dsn);
        $password = $this->getPassword($dsn);
        $host = $dsn->getHost();

        if (self::SCHEME === $dsn->getScheme()) {
            return (new EmaillabsApiTransport($user, $password, $this->client, $this->dispatcher, $this->logger))->setHost($host);
        }

        throw new UnsupportedSchemeException($dsn, self::SCHEME, $this->getSupportedSchemes());
    }
}