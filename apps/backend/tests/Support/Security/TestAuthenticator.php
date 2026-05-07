<?php

declare(strict_types=1);

namespace App\Tests\Support\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Uid\Uuid;

final class TestAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function supports(Request $request): ?bool
    {
        return $request->headers->has('X-Test-User');
    }

    public function authenticate(Request $request): Passport
    {
        $email = (string) $request->headers->get('X-Test-User');
        $roles = $this->parseRoles((string) $request->headers->get('X-Test-Roles', ''));
        $userId = (string) $request->headers->get('X-Test-User-Id', Uuid::v4()->toRfc4122());

        return new SelfValidatingPassport(new UserBadge(
            $email,
            static function () use ($email, $roles, $userId): User {
                $user = (new User())
                    ->setEmail($email)
                    ->setName('Test user')
                    ->setPassword('test-password')
                    ->setRoles($roles);

                self::forceUserId($user, $userId);

                return $user;
            },
        ));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['message' => 'TEST_AUTHENTICATION_FAILED'], Response::HTTP_UNAUTHORIZED);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(['message' => 'AUTHENTICATION_REQUIRED'], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return list<string>
     */
    private function parseRoles(string $roles): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $roles)),
            static fn (string $role): bool => '' !== $role,
        ));
    }

    private static function forceUserId(User $user, string $userId): void
    {
        if (!Uuid::isValid($userId)) {
            return;
        }

        $property = new \ReflectionProperty(User::class, 'id');
        $property->setValue($user, Uuid::fromString($userId));
    }
}
