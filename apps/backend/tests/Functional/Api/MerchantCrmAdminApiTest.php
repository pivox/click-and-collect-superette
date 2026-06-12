<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\AdminAuditLog;
use App\Entity\MerchantCrmContact;
use App\Entity\MerchantCrmProfile;
use App\Entity\User;
use App\Enum\MerchantCrmStatus;

final class MerchantCrmAdminApiTest extends FunctionalApiTestCase
{
    private const string UNKNOWN_UUID = '550e8400-e29b-41d4-a716-446655440000';

    public function testAdminUpdatesCrmCreatesProfileLazily(): void
    {
        $admin = $this->createUser('admin-crm-update@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant('merchant-crm-update@example.test');

        $response = $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s/crm', $merchant->getId()), [
            'commercial_owner' => 'Sami',
            'status' => 'client',
            'next_action_at' => '2026-06-20T10:00:00+01:00',
            'next_action_note' => 'Relancer pour la formation catalogue',
            'commercial_note' => 'Marchand motivé, deuxième supérette prévue.',
        ], $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('Sami', $payload['crm']['commercial_owner']);
        self::assertSame('client', $payload['crm']['status']);
        self::assertSame('Relancer pour la formation catalogue', $payload['crm']['next_action_note']);
        self::assertSame('Marchand motivé, deuxième supérette prévue.', $payload['crm']['commercial_note']);
        self::assertNotNull($payload['crm']['next_action_at']);
        self::assertNull($payload['crm']['last_contact_at']);
        self::assertSame([], $payload['crm']['contacts']);

        $profile = $this->entityManager->getRepository(MerchantCrmProfile::class)->findOneBy(['merchant' => $merchant]);
        self::assertNotNull($profile);
        self::assertSame(MerchantCrmStatus::Client, $profile->getStatus());

        $auditLog = $this->entityManager->getRepository(AdminAuditLog::class)->findOneBy(['action' => 'merchant.crm_update']);
        self::assertNotNull($auditLog);
        self::assertSame($merchant->getId()->toRfc4122(), $auditLog->getResourceId());
    }

    public function testAdminUpdatesCrmSelectively(): void
    {
        $admin = $this->createUser('admin-crm-selective@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant('merchant-crm-selective@example.test');
        $profile = new MerchantCrmProfile($merchant);
        $profile->setCommercialOwner('Sami');
        $profile->setNextActionAt(new \DateTimeImmutable('2026-06-20T10:00:00+01:00'));
        $profile->setNextActionNote('Appeler lundi');
        $this->entityManager->persist($profile);
        $this->entityManager->flush();

        // Only status provided — other fields must stay untouched
        $response = $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s/crm', $merchant->getId()), [
            'status' => 'client',
        ], $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('client', $payload['crm']['status']);
        self::assertSame('Sami', $payload['crm']['commercial_owner']);
        self::assertSame('Appeler lundi', $payload['crm']['next_action_note']);

        // Explicit null clears the next action
        $response = $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s/crm', $merchant->getId()), [
            'next_action_at' => null,
            'next_action_note' => null,
        ], $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertNull($payload['crm']['next_action_at']);
        self::assertNull($payload['crm']['next_action_note']);
        self::assertSame('Sami', $payload['crm']['commercial_owner']);
    }

    public function testCrmDefaultsOnMerchantWithoutProfile(): void
    {
        $admin = $this->createUser('admin-crm-defaults@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant('merchant-crm-defaults@example.test');

        $detailResponse = $this->requestJson('GET', \sprintf('/api/admin/merchants/%s', $merchant->getId()), user: $admin);
        self::assertSame(200, $detailResponse->getStatusCode());
        $detail = $this->decodeJson($detailResponse);
        self::assertSame('prospect', $detail['crm']['status']);
        self::assertNull($detail['crm']['commercial_owner']);
        self::assertNull($detail['crm']['last_contact_at']);
        self::assertSame([], $detail['crm']['contacts']);

        $listResponse = $this->requestJson('GET', '/api/admin/merchants', user: $admin);
        self::assertSame(200, $listResponse->getStatusCode());
        $list = $this->decodeJson($listResponse);
        self::assertSame('prospect', $list['items'][0]['crm']['status']);
        self::assertArrayNotHasKey('contacts', $list['items'][0]['crm']);
    }

    public function testInvalidCrmStatusReturns422(): void
    {
        $admin = $this->createUser('admin-crm-badstatus@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant('merchant-crm-badstatus@example.test');

        $response = $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s/crm', $merchant->getId()), [
            'status' => 'vip',
        ], $admin);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testInvalidNextActionAtReturns422(): void
    {
        $admin = $this->createUser('admin-crm-baddate@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant('merchant-crm-baddate@example.test');

        $response = $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s/crm', $merchant->getId()), [
            'next_action_at' => 'pas-une-date',
        ], $admin);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testAdminAddsCrmContact(): void
    {
        $admin = $this->createUser('admin-crm-contact@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant('merchant-crm-contact@example.test');

        $response = $this->requestJson('POST', \sprintf('/api/admin/merchants/%s/crm/contacts', $merchant->getId()), [
            'channel' => 'whatsapp',
            'note' => 'Relance pour activer les créneaux de retrait.',
            'contacted_at' => '2026-06-10T11:30:00+01:00',
        ], $admin);

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload['crm']['contacts']);
        self::assertSame('whatsapp', $payload['crm']['contacts'][0]['channel']);
        self::assertSame('Relance pour activer les créneaux de retrait.', $payload['crm']['contacts'][0]['note']);
        self::assertSame('admin-crm-contact@example.test', $payload['crm']['contacts'][0]['author_email']);
        self::assertSame('2026-06-10T11:30:00+01:00', $payload['crm']['contacts'][0]['contacted_at']);
        self::assertSame('2026-06-10T11:30:00+01:00', $payload['crm']['last_contact_at']);

        $auditLog = $this->entityManager->getRepository(AdminAuditLog::class)->findOneBy(['action' => 'merchant.crm_contact_add']);
        self::assertNotNull($auditLog);
        self::assertSame($merchant->getId()->toRfc4122(), $auditLog->getResourceId());
    }

    public function testContactHistoryOrderedDescAndLastContactDerivation(): void
    {
        $admin = $this->createUser('admin-crm-history@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant('merchant-crm-history@example.test');

        $first = $this->requestJson('POST', \sprintf('/api/admin/merchants/%s/crm/contacts', $merchant->getId()), [
            'channel' => 'phone',
            'note' => 'Premier appel de présentation.',
            'contacted_at' => '2026-06-08T09:00:00+00:00',
        ], $admin);
        self::assertSame(201, $first->getStatusCode());

        // Backdated contact: must appear in history but not regress last_contact_at
        $backdated = $this->requestJson('POST', \sprintf('/api/admin/merchants/%s/crm/contacts', $merchant->getId()), [
            'channel' => 'visit',
            'note' => 'Visite initiale en supérette.',
            'contacted_at' => '2026-06-01T15:00:00+00:00',
        ], $admin);
        self::assertSame(201, $backdated->getStatusCode());

        $payload = $this->decodeJson($backdated);
        self::assertCount(2, $payload['crm']['contacts']);
        self::assertSame('phone', $payload['crm']['contacts'][0]['channel']);
        self::assertSame('visit', $payload['crm']['contacts'][1]['channel']);
        self::assertSame('2026-06-08T09:00:00+00:00', $payload['crm']['last_contact_at']);

        $contacts = $this->entityManager->getRepository(MerchantCrmContact::class)->findBy(['merchant' => $merchant]);
        self::assertCount(2, $contacts);
    }

    public function testContactValidation(): void
    {
        $admin = $this->createUser('admin-crm-contactval@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant('merchant-crm-contactval@example.test');

        $unknownChannel = $this->requestJson('POST', \sprintf('/api/admin/merchants/%s/crm/contacts', $merchant->getId()), [
            'channel' => 'pigeon',
            'note' => 'Test',
        ], $admin);
        self::assertSame(422, $unknownChannel->getStatusCode());

        $emptyNote = $this->requestJson('POST', \sprintf('/api/admin/merchants/%s/crm/contacts', $merchant->getId()), [
            'channel' => 'phone',
            'note' => '   ',
        ], $admin);
        self::assertSame(422, $emptyNote->getStatusCode());

        $invalidDate = $this->requestJson('POST', \sprintf('/api/admin/merchants/%s/crm/contacts', $merchant->getId()), [
            'channel' => 'phone',
            'note' => 'Note valide',
            'contacted_at' => 'hier-soir',
        ], $admin);
        self::assertSame(422, $invalidDate->getStatusCode());
    }

    public function testListFilterByCrmStatus(): void
    {
        $admin = $this->createUser('admin-crm-filter@example.test', ['ROLE_ADMIN']);

        $clientMerchant = $this->createMerchant('merchant-crm-client@example.test');
        $clientProfile = new MerchantCrmProfile($clientMerchant);
        $clientProfile->setStatus(MerchantCrmStatus::Client);
        $this->entityManager->persist($clientProfile);

        $prospectMerchant = $this->createMerchant('merchant-crm-prospect@example.test');
        $prospectProfile = new MerchantCrmProfile($prospectMerchant);
        $this->entityManager->persist($prospectProfile);

        // Merchant without profile counts as implicit prospect
        $noProfileMerchant = $this->createMerchant('merchant-crm-noprofile@example.test');
        $this->entityManager->flush();

        $prospects = $this->decodeJson($this->requestJson('GET', '/api/admin/merchants?crm_status=prospect', user: $admin));
        self::assertSame(2, $prospects['total']);
        $prospectIds = array_map(static fn (array $item): string => $item['id'], $prospects['items']);
        self::assertContains($prospectMerchant->getId()->toRfc4122(), $prospectIds);
        self::assertContains($noProfileMerchant->getId()->toRfc4122(), $prospectIds);

        $clients = $this->decodeJson($this->requestJson('GET', '/api/admin/merchants?crm_status=client', user: $admin));
        self::assertSame(1, $clients['total']);
        self::assertSame($clientMerchant->getId()->toRfc4122(), $clients['items'][0]['id']);
        self::assertSame('client', $clients['items'][0]['crm']['status']);
    }

    public function testListFilterByCrmOwner(): void
    {
        $admin = $this->createUser('admin-crm-owner@example.test', ['ROLE_ADMIN']);

        $samiMerchant = $this->createMerchant('merchant-crm-sami@example.test');
        $samiProfile = new MerchantCrmProfile($samiMerchant);
        $samiProfile->setCommercialOwner('Sami');
        $this->entityManager->persist($samiProfile);

        $otherMerchant = $this->createMerchant('merchant-crm-leila@example.test');
        $otherProfile = new MerchantCrmProfile($otherMerchant);
        $otherProfile->setCommercialOwner('Leila');
        $this->entityManager->persist($otherProfile);

        $this->createMerchant('merchant-crm-unassigned@example.test');
        $this->entityManager->flush();

        $payload = $this->decodeJson($this->requestJson('GET', '/api/admin/merchants?crm_owner=Sami', user: $admin));
        self::assertSame(1, $payload['total']);
        self::assertSame($samiMerchant->getId()->toRfc4122(), $payload['items'][0]['id']);
        self::assertSame('Sami', $payload['items'][0]['crm']['commercial_owner']);

        // Exact match only — partial owner name matches nothing
        $partial = $this->decodeJson($this->requestJson('GET', '/api/admin/merchants?crm_owner=Sam', user: $admin));
        self::assertSame(0, $partial['total']);
    }

    public function testInvalidCrmFiltersReturn400(): void
    {
        $admin = $this->createUser('admin-crm-badfilter@example.test', ['ROLE_ADMIN']);

        $badStatus = $this->requestJson('GET', '/api/admin/merchants?crm_status=vip', user: $admin);
        self::assertSame(400, $badStatus->getStatusCode());

        $tooLongOwner = $this->requestJson('GET', '/api/admin/merchants?crm_owner='.str_repeat('a', 121), user: $admin);
        self::assertSame(400, $tooLongOwner->getStatusCode());
    }

    public function testNonAdminAccessIsRejected(): void
    {
        $customer = $this->createUser('customer-crm@example.test', ['ROLE_CUSTOMER']);
        $merchantUser = $this->createUser('merchant-user-crm@example.test', ['ROLE_MERCHANT']);
        $target = $this->createMerchant('merchant-crm-target@example.test');

        $patchUrl = \sprintf('/api/admin/merchants/%s/crm', $target->getId());
        $postUrl = \sprintf('/api/admin/merchants/%s/crm/contacts', $target->getId());

        self::assertSame(403, $this->requestJson('PATCH', $patchUrl, ['status' => 'client'], $customer)->getStatusCode());
        self::assertSame(403, $this->requestJson('PATCH', $patchUrl, ['status' => 'client'], $merchantUser)->getStatusCode());
        self::assertSame(401, $this->requestJson('PATCH', $patchUrl, ['status' => 'client'])->getStatusCode());

        self::assertSame(403, $this->requestJson('POST', $postUrl, ['channel' => 'phone', 'note' => 'x'], $customer)->getStatusCode());
        self::assertSame(403, $this->requestJson('POST', $postUrl, ['channel' => 'phone', 'note' => 'x'], $merchantUser)->getStatusCode());
        self::assertSame(401, $this->requestJson('POST', $postUrl, ['channel' => 'phone', 'note' => 'x'])->getStatusCode());
    }

    public function testUnknownMerchantReturns404(): void
    {
        $admin = $this->createUser('admin-crm-404@example.test', ['ROLE_ADMIN']);

        $patch = $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s/crm', self::UNKNOWN_UUID), [
            'status' => 'client',
        ], $admin);
        self::assertSame(404, $patch->getStatusCode());

        $post = $this->requestJson('POST', \sprintf('/api/admin/merchants/%s/crm/contacts', self::UNKNOWN_UUID), [
            'channel' => 'phone',
            'note' => 'Note',
        ], $admin);
        self::assertSame(404, $post->getStatusCode());
    }

    public function testCrmBlockPresentOnMutationResponses(): void
    {
        $admin = $this->createUser('admin-crm-regression@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant('merchant-crm-regression@example.test');
        $profile = new MerchantCrmProfile($merchant);
        $profile->setStatus(MerchantCrmStatus::Client);
        $profile->setCommercialOwner('Sami');
        $this->entityManager->persist($profile);
        $this->entityManager->flush();

        $update = $this->decodeJson($this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s', $merchant->getId()), [
            'first_name' => 'Nouveau',
        ], $admin));
        self::assertSame('client', $update['crm']['status']);
        self::assertSame('Sami', $update['crm']['commercial_owner']);

        $suspend = $this->decodeJson($this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s/suspend', $merchant->getId()), [], $admin));
        self::assertSame('client', $suspend['crm']['status']);

        $activate = $this->decodeJson($this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s/activate', $merchant->getId()), [], $admin));
        self::assertSame('client', $activate['crm']['status']);
    }

    private function createMerchant(string $email): User
    {
        $merchant = $this->createUser($email, ['ROLE_MERCHANT']);
        $this->entityManager->flush();

        return $merchant;
    }
}
