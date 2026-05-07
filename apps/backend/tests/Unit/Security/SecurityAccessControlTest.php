<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class SecurityAccessControlTest extends TestCase
{
    public function testSpecificApiAccessRulesStayBeforeGeneralApiRule(): void
    {
        $config = Yaml::parseFile(\dirname(__DIR__, 3).'/config/packages/security.yaml');
        $rules = $config['security']['access_control'] ?? [];

        $apiDocsIndex = $this->findRuleIndex(
            $rules,
            '^/api/docs(?:\\..+)?$',
            'PUBLIC_ACCESS',
            null,
        );
        $publicGetIndex = $this->findRuleIndex(
            $rules,
            '^/api/stores/[^/]+/theme$',
            'PUBLIC_ACCESS',
            ['GET'],
        );
        $merchantAreaIndex = $this->findRuleIndex(
            $rules,
            '^/api/merchant',
            'ROLE_MERCHANT',
            null,
        );
        $adminAreaIndex = $this->findRuleIndex(
            $rules,
            '^/api/admin',
            'ROLE_ADMIN',
            null,
        );
        $generalApiIndex = $this->findRuleIndex(
            $rules,
            '^/api',
            'ROLE_USER',
            null,
        );

        self::assertNotNull($apiDocsIndex);
        self::assertNotNull($publicGetIndex);
        self::assertNotNull($merchantAreaIndex);
        self::assertNotNull($adminAreaIndex);
        self::assertNotNull($generalApiIndex);
        self::assertLessThan($generalApiIndex, $apiDocsIndex);
        self::assertLessThan($generalApiIndex, $publicGetIndex);
        self::assertLessThan($generalApiIndex, $merchantAreaIndex);
        self::assertLessThan($generalApiIndex, $adminAreaIndex);
    }

    /**
     * @param list<array<string, mixed>> $rules
     * @param list<string>|null          $methods
     */
    private function findRuleIndex(array $rules, string $path, string|array $role, ?array $methods): ?int
    {
        foreach ($rules as $index => $rule) {
            if (($rule['path'] ?? null) !== $path) {
                continue;
            }

            if (($rule['roles'] ?? null) !== $role) {
                continue;
            }

            if (($rule['methods'] ?? null) !== $methods) {
                continue;
            }

            return $index;
        }

        return null;
    }
}
