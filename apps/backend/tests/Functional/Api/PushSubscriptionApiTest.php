<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\PushSubscription;
use App\Repository\PushSubscriptionRepository;

final class PushSubscriptionApiTest extends FunctionalApiTestCase
{
    public function testRegisterExistingEndpointRefreshesKeysAndScope(): void
    {
        $customer = $this->createUser('push-customer@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('push-merchant@example.test', ['ROLE_MERCHANT']);
        $endpoint = 'https://updates.push.services.mozilla.com/wpush/v2/shared-endpoint';

        $firstResponse = $this->requestJson('POST', '/api/me/push-subscriptions', [
            'endpoint' => $endpoint,
            'p256dhKey' => 'old-p256dh',
            'authKey' => 'old-auth',
            'userAgent' => 'Old Browser',
        ], $customer);

        self::assertSame(201, $firstResponse->getStatusCode());

        $secondResponse = $this->requestJson('POST', '/api/merchant/push-subscriptions', [
            'endpoint' => $endpoint,
            'p256dhKey' => 'new-p256dh',
            'authKey' => 'new-auth',
            'userAgent' => 'New Browser',
        ], $merchant);

        self::assertSame(201, $secondResponse->getStatusCode());

        /** @var PushSubscriptionRepository $repository */
        $repository = $this->entityManager->getRepository(PushSubscription::class);
        $subscriptions = $repository->findAll();

        self::assertCount(1, $subscriptions);
        $subscription = $subscriptions[0];
        self::assertInstanceOf(PushSubscription::class, $subscription);
        self::assertTrue($subscription->getUser()->getId()->equals($merchant->getId()));
        self::assertSame('new-p256dh', $subscription->getP256dhKey());
        self::assertSame('new-auth', $subscription->getAuthKey());
        self::assertSame('merchant', $subscription->getScope());
        self::assertSame('New Browser', $subscription->getUserAgent());
    }
}
