<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\MessengerWorkerStateRecorder;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

final readonly class MessengerWorkerStateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MessengerWorkerStateRecorder $recorder,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<class-string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageHandledEvent::class => 'onMessageHandled',
        ];
    }

    public function onMessageHandled(WorkerMessageHandledEvent $event): void
    {
        if ('async' !== $event->getReceiverName()) {
            return;
        }

        try {
            $this->recorder->recordConsumedMessage('async', new \DateTimeImmutable());
        } catch (\Throwable $exception) {
            $this->logger->warning('messenger.worker_state.record_failed', [
                'exception' => $exception::class,
            ]);
        }
    }
}
