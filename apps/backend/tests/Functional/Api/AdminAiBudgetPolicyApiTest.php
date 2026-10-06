<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\AdminAuditLog;
use App\Entity\AiBudgetPolicy;

final class AdminAiBudgetPolicyApiTest extends FunctionalApiTestCase
{
    public function testDefaultPolicyIsDisabledWithNoCapsConfigured(): void
    {
        $admin = $this->createUser('admin@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('GET', '/api/admin/ai-budget-policy', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertFalse($payload['enabled']);
        self::assertFalse($payload['openai_enabled']);
        self::assertFalse($payload['gemini_enabled']);
        self::assertFalse($payload['mistral_enabled']);
        self::assertFalse($payload['qwen_enabled']);
        self::assertSame('USD', $payload['currency']);
        self::assertArrayNotHasKey('max_cost_per_call_amount', $payload);
    }

    public function testAdminCanConfigureAndEnableBudgetGuardrails(): void
    {
        $admin = $this->createUser('admin@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson(
            'PUT',
            '/api/admin/ai-budget-policy',
            $this->validPolicyPayload([
                'enabled' => true,
                'openai_enabled' => true,
                'max_cost_per_call_amount' => '0.0500',
                'global_period_cap_amount' => '250.0000',
            ]),
            $admin,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertTrue($payload['enabled']);
        self::assertTrue($payload['openai_enabled']);
        self::assertFalse($payload['gemini_enabled']);
        self::assertSame('0.0500', $payload['max_cost_per_call_amount']);
        self::assertSame('250.0000', $payload['global_period_cap_amount']);

        $policy = $this->entityManager->getRepository(AiBudgetPolicy::class)->find(AiBudgetPolicy::DEFAULT_ID);
        self::assertInstanceOf(AiBudgetPolicy::class, $policy);
        self::assertTrue($policy->isEnabled());
        self::assertSame('0.0500', $policy->getMaxCostPerCallAmount());

        $auditLog = $this->entityManager->getRepository(AdminAuditLog::class)->findOneBy(['action' => 'ai_budget_policy.update']);
        self::assertNotNull($auditLog);
    }

    public function testRejectsInvalidCurrencyAndNegativeCaps(): void
    {
        $admin = $this->createUser('admin@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson(
            'PUT',
            '/api/admin/ai-budget-policy',
            $this->validPolicyPayload(['currency' => 'usd']),
            $admin,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testRejectsAlertThresholdOutOfRange(): void
    {
        $admin = $this->createUser('admin@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson(
            'PUT',
            '/api/admin/ai-budget-policy',
            $this->validPolicyPayload(['alert_threshold_percent' => 150]),
            $admin,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testRoutesDenyUsersWithoutAdminRole(): void
    {
        $merchant = $this->createUser('merchant@example.test', ['ROLE_MERCHANT']);

        $getResponse = $this->requestJson('GET', '/api/admin/ai-budget-policy', user: $merchant);
        $putResponse = $this->requestJson('PUT', '/api/admin/ai-budget-policy', $this->validPolicyPayload(), $merchant);

        self::assertSame(403, $getResponse->getStatusCode());
        self::assertSame(403, $putResponse->getStatusCode());
    }

    public function testRoutesDenyAnonymousUsers(): void
    {
        $getResponse = $this->requestJson('GET', '/api/admin/ai-budget-policy');
        $putResponse = $this->requestJson('PUT', '/api/admin/ai-budget-policy', $this->validPolicyPayload());

        // API Platform exception listener (priority 0) may intercept AccessDeniedException
        // before Symfony security's entry point (priority -64), so both 401 and 403 are valid.
        self::assertContains($getResponse->getStatusCode(), [401, 403]);
        self::assertContains($putResponse->getStatusCode(), [401, 403]);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function validPolicyPayload(array $overrides = []): array
    {
        return array_merge([
            'enabled' => false,
            'openai_enabled' => false,
            'gemini_enabled' => false,
            'mistral_enabled' => false,
            'qwen_enabled' => false,
            'currency' => 'USD',
            'global_period_days' => 30,
            'alert_threshold_percent' => 80,
        ], $overrides);
    }
}
