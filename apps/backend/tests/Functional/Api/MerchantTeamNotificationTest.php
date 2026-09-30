<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Kadhia;
use App\Entity\MerchantMembership;
use App\Entity\MerchantOrganization;
use App\Entity\Notification;
use App\Entity\Order;
use App\Entity\Shop;
use App\Entity\User;
use App\Service\NotificationService;

/**
 * MERCHANT-TEAM-005: every eligible account of the organization receives its
 * own merchant notification with an independent read state; excluded accounts
 * receive nothing; retries stay idempotent per account.
 */
final class MerchantTeamNotificationTest extends FunctionalApiTestCase
{
    public function testEveryActiveAccountGetsItsOwnNotificationIdempotently(): void
    {
        [$organization, $primary, $shop] = $this->organizationWithShop('notif-primary@example.test');
        $secondary = $this->createUser('notif-secondary@example.test', ['ROLE_MERCHANT']);
        $this->activeMembership($organization, $secondary);
        $order = $this->orderFor($shop);
        $this->entityManager->flush();

        $service = $this->notificationService();
        $service->notifyMerchantOrderSubmitted($order);
        $this->entityManager->flush();

        // Retry of the same event: no duplicate per account.
        $service->notifyMerchantOrderSubmitted($order);
        $this->entityManager->flush();

        $notifications = $this->entityManager->getRepository(Notification::class)->findBy([
            'type' => NotificationService::TYPE_MERCHANT_ORDER_SUBMITTED,
        ]);
        self::assertCount(2, $notifications);
        $recipients = array_map(
            static fn (Notification $notification): string => $notification->getUser()->getEmail(),
            $notifications,
        );
        sort($recipients);
        self::assertSame(['notif-primary@example.test', 'notif-secondary@example.test'], $recipients);
    }

    public function testEachAccountManagesItsOwnReadState(): void
    {
        [$organization, $primary, $shop] = $this->organizationWithShop('notif-read-primary@example.test');
        $secondary = $this->createUser('notif-read-secondary@example.test', ['ROLE_MERCHANT']);
        $this->activeMembership($organization, $secondary);
        $order = $this->orderFor($shop);
        $this->entityManager->flush();

        $this->notificationService()->notifyMerchantOrderSubmitted($order);
        $this->entityManager->flush();

        // The secondary account marks their own notification as read…
        $secondaryList = $this->decodeJson($this->requestJson('GET', '/api/merchant/notifications', user: $secondary));
        self::assertCount(1, $secondaryList['items']);
        $read = $this->requestJson(
            'PATCH',
            \sprintf('/api/merchant/notifications/%s/read', $secondaryList['items'][0]['id']),
            [],
            $secondary,
        );
        self::assertSame(200, $read->getStatusCode());

        // …without touching the primary account's one.
        $primaryList = $this->decodeJson($this->requestJson('GET', '/api/merchant/notifications', user: $primary));
        self::assertCount(1, $primaryList['items']);
        self::assertFalse($primaryList['items'][0]['is_read']);

        $secondaryAfter = $this->decodeJson($this->requestJson('GET', '/api/merchant/notifications', user: $secondary));
        self::assertTrue($secondaryAfter['items'][0]['is_read']);
    }

    public function testRevokedInvitedAndInactiveAccountsReceiveNothing(): void
    {
        [$organization, $primary, $shop] = $this->organizationWithShop('notif-excl-primary@example.test');

        $revoked = $this->createUser('notif-excl-revoked@example.test', ['ROLE_MERCHANT']);
        $revokedMembership = (new MerchantMembership())
            ->setOrganization($organization)->setUser($revoked)->revoke($primary);
        $this->entityManager->persist($revokedMembership);

        $invited = $this->createUser('notif-excl-invited@example.test', ['ROLE_MERCHANT']);
        $invitedMembership = (new MerchantMembership())
            ->setOrganization($organization)->setUser($invited)->markInvited($primary);
        $this->entityManager->persist($invitedMembership);

        $inactive = $this->createUser('notif-excl-inactive@example.test', ['ROLE_MERCHANT']);
        $inactive->setActive(false);
        $this->activeMembership($organization, $inactive);

        $order = $this->orderFor($shop);
        $this->entityManager->flush();

        $this->notificationService()->notifyMerchantOrderSubmitted($order);
        $this->entityManager->flush();

        $notifications = $this->entityManager->getRepository(Notification::class)->findBy([
            'type' => NotificationService::TYPE_MERCHANT_ORDER_SUBMITTED,
        ]);
        self::assertCount(1, $notifications);
        self::assertSame('notif-excl-primary@example.test', $notifications[0]->getUser()->getEmail());
    }

    public function testInactiveOrganizationSuppressesMerchantNotifications(): void
    {
        [$organization, $primary, $shop] = $this->organizationWithShop('notif-orgoff-primary@example.test');
        $organization->setActive(false);
        $order = $this->orderFor($shop);
        $this->entityManager->flush();

        $this->notificationService()->notifyMerchantOrderSubmitted($order);
        $this->entityManager->flush();

        self::assertCount(0, $this->entityManager->getRepository(Notification::class)->findAll());
    }

    public function testShopWithoutOrganizationFallsBackToActiveOwner(): void
    {
        $owner = $this->createUser('notif-legacy-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($owner);
        $order = $this->orderFor($shop);
        $this->entityManager->flush();

        $this->notificationService()->notifyMerchantOrderCancelled($order);
        $this->entityManager->flush();

        $notifications = $this->entityManager->getRepository(Notification::class)->findBy([
            'type' => NotificationService::TYPE_MERCHANT_ORDER_CANCELLED,
        ]);
        self::assertCount(1, $notifications);
        self::assertSame($owner->getId()->toRfc4122(), $notifications[0]->getUser()->getId()->toRfc4122());
    }

    // Fixtures

    /**
     * @return array{0: MerchantOrganization, 1: User, 2: Shop}
     */
    private function organizationWithShop(string $primaryEmail): array
    {
        $primary = $this->createUser($primaryEmail, ['ROLE_MERCHANT']);
        $organization = (new MerchantOrganization())
            ->setName('Organisation Notifications')
            ->setPrimaryAccount($primary);
        $this->entityManager->persist($organization);
        $this->activeMembership($organization, $primary);
        $shop = $this->createShop($primary);
        $shop->setMerchantOrganization($organization);

        return [$organization, $primary, $shop];
    }

    private function activeMembership(MerchantOrganization $organization, User $user): void
    {
        $membership = (new MerchantMembership())
            ->setOrganization($organization)
            ->setUser($user)
            ->activate();
        $this->entityManager->persist($membership);
    }

    private function orderFor(Shop $shop): Order
    {
        $customer = $this->createUser('notif-customer-'.uniqid().'@example.test', ['ROLE_CUSTOMER']);
        $kadhia = (new Kadhia())->setCustomer($customer)->setShop($shop);
        $this->entityManager->persist($kadhia);
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setKadhia($kadhia);
        $this->entityManager->persist($order);

        return $order;
    }

    private function notificationService(): NotificationService
    {
        $service = self::getContainer()->get(NotificationService::class);
        self::assertInstanceOf(NotificationService::class, $service);

        return $service;
    }
}
