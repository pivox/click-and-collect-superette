<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Incident;
use App\Entity\Order;
use App\Entity\Shop;
use App\Enum\IncidentType;
use Symfony\Component\Uid\Uuid;

final class AdminIncidentApiTest extends FunctionalApiTestCase
{
    public function testAdminCanListAndFilterIncidents(): void
    {
        $admin = $this->createUser('admin-incident-list@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-incident-list@example.test', ['ROLE_MERCHANT']);
        $customer = $this->createUser('customer-incident-list@example.test', ['ROLE_CUSTOMER']);
        $incident = $this->createIncident($merchant, $customer, IncidentType::MissingProduct);
        $otherMerchant = $this->createUser('merchant-incident-other@example.test', ['ROLE_MERCHANT']);
        $otherCustomer = $this->createUser('customer-incident-other@example.test', ['ROLE_CUSTOMER']);
        $otherIncident = $this->createIncident($otherMerchant, $otherCustomer, IncidentType::QrIssue);
        $otherIncident->startProcessing(new \DateTimeImmutable('2026-06-05T13:00:00+01:00'));
        $this->entityManager->flush();

        $response = $this->requestJson('GET', \sprintf(
            '/api/admin/incidents?merchant=%s&client=%s&status=open&page=1&limit=1',
            $merchant->getId()->toRfc4122(),
            $customer->getId()->toRfc4122(),
        ), user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertSame(1, $payload['page']);
        self::assertSame(1, $payload['limit']);
        self::assertCount(1, $payload['items']);
        self::assertSame($incident->getId()->toRfc4122(), $payload['items'][0]['id']);
        self::assertSame('missing_product', $payload['items'][0]['type']);
        self::assertSame('open', $payload['items'][0]['status']);
        self::assertSame('merchant-incident-list@example.test', $payload['items'][0]['merchant_email']);
        self::assertSame('customer-incident-list@example.test', $payload['items'][0]['customer_email']);
    }

    public function testAdminCanReadIncidentDetailWithHistory(): void
    {
        $admin = $this->createUser('admin-incident-detail@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-incident-detail@example.test', ['ROLE_MERCHANT']);
        $customer = $this->createUser('customer-incident-detail@example.test', ['ROLE_CUSTOMER']);
        $incident = $this->createIncident($merchant, $customer, IncidentType::CustomerAbsent);
        $incident->startProcessing(new \DateTimeImmutable('2026-06-05T14:00:00+01:00'));
        $incident->close(new \DateTimeImmutable('2026-06-05T15:00:00+01:00'));
        $this->entityManager->flush();

        $response = $this->requestJson('GET', \sprintf('/api/admin/incidents/%s', $incident->getId()), user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($incident->getId()->toRfc4122(), $payload['id']);
        self::assertSame($incident->getOrder()->getId()->toRfc4122(), $payload['order_id']);
        self::assertSame('customer_absent', $payload['type']);
        self::assertCount(0, $payload['notes']);
        self::assertSame('incident.opened', $payload['history'][0]['event']);
        self::assertSame('incident.processing_started', $payload['history'][1]['event']);
        self::assertSame('2026-06-05T14:00:00+01:00', $payload['history'][1]['occurred_at']);
        self::assertSame('incident.closed', $payload['history'][2]['event']);
        self::assertSame('2026-06-05T15:00:00+01:00', $payload['history'][2]['occurred_at']);
    }

    public function testAdminCanAddInternalNoteAndCloseIncident(): void
    {
        $admin = $this->createUser('admin-incident-note@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-incident-note@example.test', ['ROLE_MERCHANT']);
        $customer = $this->createUser('customer-incident-note@example.test', ['ROLE_CUSTOMER']);
        $incident = $this->createIncident($merchant, $customer, IncidentType::PriceError);

        $noteResponse = $this->requestJson('POST', \sprintf('/api/admin/incidents/%s/notes', $incident->getId()), [
            'note' => 'Client rappelé, erreur de prix confirmée.',
        ], $admin);
        self::assertSame(201, $noteResponse->getStatusCode());
        $notePayload = $this->decodeJson($noteResponse);
        self::assertCount(1, $notePayload['notes']);
        self::assertSame('Client rappelé, erreur de prix confirmée.', $notePayload['notes'][0]['note']);
        self::assertSame($admin->getId()->toRfc4122(), $notePayload['notes'][0]['created_by_admin_id']);
        self::assertSame('incident.note_added', $notePayload['history'][1]['event']);

        $startResponse = $this->requestJson('PATCH', \sprintf('/api/admin/incidents/%s/start-processing', $incident->getId()), [], $admin);
        self::assertSame(200, $startResponse->getStatusCode(), (string) $startResponse->getContent());
        self::assertSame('in_progress', $this->decodeJson($startResponse)['status']);

        $closeResponse = $this->requestJson('PATCH', \sprintf('/api/admin/incidents/%s/close', $incident->getId()), [], $admin);
        self::assertSame(200, $closeResponse->getStatusCode(), (string) $closeResponse->getContent());
        $closePayload = $this->decodeJson($closeResponse);
        self::assertSame('closed', $closePayload['status']);
        self::assertNotNull($closePayload['closed_at']);
        self::assertSame('incident.closed', $closePayload['history'][3]['event']);
    }

    public function testInvalidIncidentFiltersReturn400(): void
    {
        $admin = $this->createUser('admin-incident-invalid-filter@example.test', ['ROLE_ADMIN']);

        self::assertSame(400, $this->requestJson('GET', '/api/admin/incidents?status=bad', user: $admin)->getStatusCode());
        self::assertSame(400, $this->requestJson('GET', '/api/admin/incidents?merchant=bad-uuid', user: $admin)->getStatusCode());
        self::assertSame(400, $this->requestJson('GET', '/api/admin/incidents?client=bad-uuid', user: $admin)->getStatusCode());
    }

    public function testMerchantIsForbiddenFromAdminIncidentEndpoints(): void
    {
        $merchant = $this->createUser('merchant-incident-forbidden@example.test', ['ROLE_MERCHANT']);
        $customer = $this->createUser('customer-incident-forbidden@example.test', ['ROLE_CUSTOMER']);
        $incident = $this->createIncident($merchant, $customer, IncidentType::Other);

        self::assertSame(403, $this->requestJson('GET', '/api/admin/incidents', user: $merchant)->getStatusCode());
        self::assertSame(403, $this->requestJson('GET', \sprintf('/api/admin/incidents/%s', $incident->getId()), user: $merchant)->getStatusCode());
        self::assertSame(403, $this->requestJson('POST', \sprintf('/api/admin/incidents/%s/notes', $incident->getId()), ['note' => 'X'], $merchant)->getStatusCode());
        self::assertSame(403, $this->requestJson('PATCH', \sprintf('/api/admin/incidents/%s/close', $incident->getId()), [], $merchant)->getStatusCode());
    }

    private function createIncident(\App\Entity\User $merchant, \App\Entity\User $customer, IncidentType $type): Incident
    {
        $shopId = Uuid::v4()->toRfc4122();
        $shop = (new Shop())
            ->setName('Supérette Incident '.$shopId)
            ->setSlug('superette-incident-'.$shopId)
            ->setQrCodeToken('qr-incident-'.$shopId)
            ->setOwner($merchant);
        $order = (new Order())->setShop($shop)->setCustomer($customer);
        $order->submit();
        $incident = Incident::open(
            $order,
            $type,
            new \DateTimeImmutable('2026-06-05T12:30:00+01:00'),
            new \DateTimeImmutable('2026-06-05T13:30:00+01:00'),
        );

        $this->entityManager->persist($shop);
        $this->entityManager->persist($order);
        $this->entityManager->persist($incident);
        $this->entityManager->flush();

        return $incident;
    }
}
