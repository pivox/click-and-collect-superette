<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PushSubscription;
use App\Entity\User;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class WebPushService
{
    private const TIMEOUT_SECONDS = 2;

    public function __construct(
        private PushSubscriptionRepository $pushSubscriptionRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        #[Autowire('%env(VAPID_PUBLIC_KEY)?%')]
        private ?string $vapidPublicKey = null,
        #[Autowire('%env(VAPID_PRIVATE_KEY)?%')]
        private ?string $vapidPrivateKey = null,
        #[Autowire('%env(VAPID_SUBJECT)?%')]
        private ?string $vapidSubject = null,
    ) {
    }

    public function sendToUser(User $user, string $titleFr, string $bodyFr, ?string $url = null): void
    {
        $subscriptions = $this->pushSubscriptionRepository->findByUser($user);

        foreach ($subscriptions as $subscription) {
            $this->sendOneNotification($subscription, $titleFr, $bodyFr, $url);
        }
    }

    private function sendOneNotification(PushSubscription $subscription, string $titleFr, string $bodyFr, ?string $url): void
    {
        // Skip if VAPID keys are not configured (e.g., in test environment)
        if (null === $this->vapidPublicKey || null === $this->vapidPrivateKey || null === $this->vapidSubject) {
            return;
        }

        try {
            $webPush = new WebPush(
                ['VAPID' => [
                    'subject' => $this->vapidSubject,
                    'publicKey' => $this->vapidPublicKey,
                    'privateKey' => $this->vapidPrivateKey,
                ]],
                [],
                self::TIMEOUT_SECONDS,
            );

            $pushSubscription = Subscription::create([
                'endpoint' => $subscription->getEndpoint(),
                'publicKey' => $subscription->getP256dhKey(),
                'authToken' => $subscription->getAuthKey(),
            ]);

            $payload = json_encode([
                'title' => $titleFr,
                'body' => $bodyFr,
                'url' => $url ?? '/',
            ], \JSON_THROW_ON_ERROR);

            $webPush->queueNotification($pushSubscription, $payload);

            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    $subscription->setLastSuccessAt(new \DateTimeImmutable());
                    $this->entityManager->flush();
                } else {
                    $this->logger->warning('push_delivery_failed', [
                        'subscription_id' => $subscription->getId()->toRfc4122(),
                        'reason' => $report->getReason(),
                        'expired' => $report->isSubscriptionExpired(),
                    ]);

                    if ($report->isSubscriptionExpired()) {
                        // Clean up expired subscription
                        $this->entityManager->remove($subscription);
                        $this->entityManager->flush();
                    } else {
                        $subscription->setLastFailureAt(new \DateTimeImmutable());
                        $subscription->setFailureCount($subscription->getFailureCount() + 1);
                        $this->entityManager->flush();
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('push_send_failed', [
                'subscription_id' => $subscription->getId()->toRfc4122(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
