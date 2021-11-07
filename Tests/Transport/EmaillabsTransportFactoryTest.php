<?php

declare(strict_types=1);

namespace Shepherdmat\Symfony\Mailer\Emaillabs\Tests\Transport;

use Shepherdmat\Symfony\Mailer\Emaillabs\Transport\EmaillabsApiTransport;
use Shepherdmat\Symfony\Mailer\Emaillabs\Transport\EmaillabsTransportFactory;
use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class EmaillabsTransportFactoryTest extends TransportFactoryTestCase
{

    public function getFactory(): TransportFactoryInterface
    {
        return new EmaillabsTransportFactory($this->getDispatcher(), $this->getClient(), $this->getLogger());
    }

    public function supportsProvider(): iterable
    {
        yield [
            new Dsn(EmaillabsTransportFactory::SCHEME, 'default'),
            true,
        ];
    }

    public function createProvider(): iterable
    {
        $client = $this->getClient();
        $dispatcher = $this->getDispatcher();
        $logger = $this->getLogger();

        yield [
            new Dsn(EmaillabsTransportFactory::SCHEME, 'default', self::USER, self::PASSWORD),
            (new EmaillabsApiTransport(self::USER, self::PASSWORD, $client, $dispatcher, $logger))->setHost('default'),
        ];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn(sprintf('%s+foo', EmaillabsTransportFactory::SCHEME), 'asd', self::USER, self::PASSWORD),
            'The "emaillabs+foo" scheme is not supported; supported schemes for mailer "emaillabs" are: "emaillabs".',
        ];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield [new Dsn(EmaillabsTransportFactory::SCHEME, 'default')];
        yield [new Dsn(EmaillabsTransportFactory::SCHEME, 'default', self::USER)];
    }
}