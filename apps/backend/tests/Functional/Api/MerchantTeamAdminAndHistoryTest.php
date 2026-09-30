<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Kadhia;
use App\Entity\MerchantMembership;
use App\Entity\MerchantOrganization;
use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Enum\OrderStatusActorType;
use App\Service\OrderStatusLogRecorder;

/**
 * MERCHANT-TEAM-006: admin diagnostic block on the merchant detail and
 * transition author exposed on the merchant status history (never on the
 * customer one).
 */
final class MerchantTeamAdminAndHistoryTest extends FunctionalApiTestCase
{
    public function testAdminMerchantDetailExposesTheOrganizationBlock(): void
    {
        $admin = $this->createUser('admin-team-view@example.test', ['ROLE_ADMIN']);
        $primary = $this->createUser('team-view-primary@example.test', ['ROLE_MERCHANT']);
        $secondary = $this->createUser('team-view-secondary@example.test', ['ROLE_MERCHANT']);
        $organization = (new MerchantOrganization())
            ->setName('Organisation Vue Admin')
            ->setPrimaryAccount($primary);
        $this->entityManager->persist($organization);
        $this->entityManager->persist((new MerchantMembership())->setOrganization($organization)->setUser($primary)->activate());
        $this->entityManager->persist((new MerchantMembership())->setOrganization($organization)->setUser($secondary)->markInvited($primary));
        $this->entityManager->flush();

        $response = $this->requestJson('GET', \sprintf('/api/admin/merchants/%s', $primary->getId()), user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($organization->getId()->toRfc4122(), $payload['organization']['id']);
        self::assertSame('Organisation Vue Admin', $payload['organization']['name']);
        self::assertTrue($payload['organization']['is_primary']);
        self::assertSame(2, $payload['organization']['accounts_count']);
        self::assertCount(2, $payload['organization']['accounts']);

        $byEmail = array_column($payload['organization']['accounts'], null, 'email');
        self::assertSame('active', $byEmail['team-view-primary@example.test']['status']);
        self::assertTrue($byEmail['team-view-primary@example.test']['is_primary']);
        self::assertSame('invited', $byEmail['team-view-secondary@example.test']['status']);
        self::assertNotNull($byEmail['team-view-secondary@example.test']['invited_at']);
    }

    public function testAdminMerchantDetailWithoutOrganizationOmitsTheBlock(): void
    {
        $admin = $this->createUser('admin-team-legacy@example.test', ['ROLE_ADMIN']);
        $legacy = $this->createUser('team-legacy-merchant@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson('GET', \sprintf('/api/admin/merchants/%s', $legacy->getId()), user: $admin);

        self::assertSame(200, $response->getStatusCode());
        self::assertArrayNotHasKey('organization', $this->decodeJson($response));
    }

    public function testMerchantStatusHistoryExposesTheAuthorButCustomerHistoryDoesNot(): void
    {
        $owner = $this->createUser('history-owner@example.test', ['ROLE_MERCHANT']);
        $owner->setFirstName('Ahmed');
        $customer = $this->createUser('history-customer@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop($owner);
        $kadhia = (new Kadhia())->setCustomer($customer)->setShop($shop);
        $this->entityManager->persist($kadhia);
        $order = (new Order())->setCustomer($customer)->setShop($shop)->setKadhia($kadhia);
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $recorder = self::getContainer()->get(OrderStatusLogRecorder::class);
        self::assertInstanceOf(OrderStatusLogRecorder::class, $recorder);
        $recorder->record($order, OrderStatus::Submitted, actorUser: $customer, actorType: OrderStatusActorType::Customer);
        $recorder->record($order, OrderStatus::Accepted, actorUser: $owner, actorType: OrderStatusActorType::Merchant);
        $this->entityManager->flush();

        $merchantHistory = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/%s/status-history', $shop->getId(), $order->getId()),
            user: $owner,
        );
        self::assertSame(200, $merchantHistory->getStatusCode());
        $transitions = array_column($this->decodeJson($merchantHistory)['transitions'], null, 'status');
        self::assertSame('customer', $transitions['submitted']['actor_type']);
        self::assertSame('merchant', $transitions['accepted']['actor_type']);
        self::assertSame('Ahmed', $transitions['accepted']['actor_name']);

        // Customer route: author fields stay absent (staff names never leak).
        $customerHistory = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s/status-history', $order->getId()),
            user: $customer,
        );
        self::assertSame(200, $customerHistory->getStatusCode());
        foreach ($this->decodeJson($customerHistory)['transitions'] as $transition) {
            self::assertArrayNotHasKey('actor_type', $transition);
            self::assertArrayNotHasKey('actor_name', $transition);
        }
    }
}
