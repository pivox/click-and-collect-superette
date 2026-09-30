<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\RequestIdResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class RequestIdResolverTest extends TestCase
{
    private RequestIdResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new RequestIdResolver();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validClientIdProvider(): iterable
    {
        yield 'uuid v4' => ['550e8400-e29b-41d4-a716-446655440000'];
        yield 'minimum 8 chars' => ['abcd1234'];
        yield 'maximum 64 chars' => [str_repeat('a', 64)];
        yield 'alphanumeric with dashes' => ['mobile-app-42-ABC'];
    }

    #[DataProvider('validClientIdProvider')]
    public function testKeepsValidClientProvidedId(string $clientId): void
    {
        self::assertSame($clientId, $this->resolver->resolve($clientId));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function invalidClientIdProvider(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'too short (7 chars)' => ['abc1234'];
        yield 'too long (65 chars)' => [str_repeat('a', 65)];
        yield 'underscore' => ['request_id_1'];
        yield 'space' => ['request id 1'];
        yield 'unicode' => ['requête-éöü-123'];
        yield 'injection attempt' => ["abcd1234\r\nX-Evil: 1"];
    }

    #[DataProvider('invalidClientIdProvider')]
    public function testReplacesInvalidClientIdWithServerGeneratedUuid(?string $clientId): void
    {
        $resolved = $this->resolver->resolve($clientId);

        self::assertNotSame($clientId, $resolved);
        self::assertTrue(Uuid::isValid($resolved));
    }

    public function testGeneratedIdsAreUniquePerCall(): void
    {
        self::assertNotSame($this->resolver->resolve(null), $this->resolver->resolve(null));
    }
}
