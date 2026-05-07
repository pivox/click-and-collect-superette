<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class SecurityAccessControlTest extends TestCase
{
    public function testStoreThemeGetStaysPublicAndMerchantWriteRuleExists(): void
    {
        $config = Yaml::parseFile(dirname(__DIR__, 3).'/config/packages/security.yaml');
        $rules = $config['security']['access_control'] ?? [];

        $publicGetIndex = $this->findRuleIndex(
            $rules,
            '^/api/stores/[^/]+/theme$',
            'PUBLIC_ACCESS',
            ['GET'],
        );
        $merchantWriteIndex = $this->findRuleIndex(
            $rules,
            '^/api/stores/[^/]+/theme$',
            'ROLE_MERCHANT',
            ['POST', 'PUT', 'DELETE'],
        );
        $generalApiIndex = $this->findRuleIndex(
            $rules,
            '^/api',
            'ROLE_USER',
            null,
        );

        self::assertNotNull($publicGetIndex);
        self::assertNotNull($merchantWriteIndex);
        self::assertNotNull($generalApiIndex);
        self::assertLessThan($generalApiIndex, $publicGetIndex);
        self::assertLessThan($generalApiIndex, $merchantWriteIndex);
    }

    /**
     * @param list<array<string, mixed>> $rules
     * @param list<string>|null $methods
     */
    private function findRuleIndex(array $rules, string $path, string $role, ?array $methods): ?int
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
