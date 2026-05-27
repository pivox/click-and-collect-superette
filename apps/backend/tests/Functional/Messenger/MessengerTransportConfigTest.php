<?php

declare(strict_types=1);

namespace App\Tests\Functional\Messenger;

use App\Message\ExpireMerchantResponseMessage;
use App\Message\ExpirePartialAcceptanceMessage;
use App\Message\PartialAcceptanceReminderMessage;
use App\Message\SendPickupReminderMessage;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Verifies that all deferred message types are routed to the async transport.
 * Uses in-memory:// transport (configured in when@test) — no real broker required.
 */
final class MessengerTransportConfigTest extends KernelTestCase
{
    private MessageBusInterface $bus;
    private InMemoryTransport $transport;

    protected function setUp(): void
    {
        $databaseUrl = \sprintf('sqlite:///%s/kadhia_messenger_config_test.db', \sys_get_temp_dir());
        $_ENV['DATABASE_URL'] = $databaseUrl;
        $_SERVER['DATABASE_URL'] = $databaseUrl;

        self::bootKernel();

        $this->bus = self::getContainer()->get(MessageBusInterface::class);
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $this->transport = $transport;
    }

    public function testExpireMerchantResponseMessageRoutesToAsync(): void
    {
        $this->bus->dispatch(new ExpireMerchantResponseMessage('00000000-0000-0000-0000-000000000001'));

        $messages = $this->getSentMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(ExpireMerchantResponseMessage::class, $messages[0]);
    }

    public function testExpirePartialAcceptanceMessageRoutesToAsync(): void
    {
        $this->bus->dispatch(new ExpirePartialAcceptanceMessage('00000000-0000-0000-0000-000000000002'));

        $messages = $this->getSentMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(ExpirePartialAcceptanceMessage::class, $messages[0]);
    }

    public function testPartialAcceptanceReminderMessageRoutesToAsync(): void
    {
        $this->bus->dispatch(new PartialAcceptanceReminderMessage('00000000-0000-0000-0000-000000000003'));

        $messages = $this->getSentMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(PartialAcceptanceReminderMessage::class, $messages[0]);
    }

    public function testSendPickupReminderMessageRoutesToAsync(): void
    {
        $this->bus->dispatch(new SendPickupReminderMessage('00000000-0000-0000-0000-000000000004'));

        $messages = $this->getSentMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(SendPickupReminderMessage::class, $messages[0]);
    }

    /**
     * @return list<object>
     */
    private function getSentMessages(): array
    {
        return array_map(
            static fn (Envelope $envelope): object => $envelope->getMessage(),
            $this->transport->getSent(),
        );
    }
}
