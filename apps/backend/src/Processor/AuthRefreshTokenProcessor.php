<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AuthSessionOutput;
use App\Dto\AuthRefreshInput;
use App\Service\RefreshTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Single-use refresh token rotation (#616).
 *
 * The presented token is consumed and a new {JWT, refresh token} pair is
 * issued in the same rotation family. Replaying a consumed token revokes the
 * whole family (theft detection). All failures are generic 401s — no account
 * or token enumeration, and the raw token is never logged.
 *
 * @implements ProcessorInterface<AuthRefreshInput, AuthSessionOutput>
 */
final readonly class AuthRefreshTokenProcessor implements ProcessorInterface
{
    public function __construct(
        private RefreshTokenManager $refreshTokenManager,
        private JWTTokenManagerInterface $jwtTokenManager,
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
        #[Autowire(param: 'lexik_jwt_authentication.token_ttl')]
        private int $jwtTokenTtl,
        #[Autowire(service: 'monolog.logger.security')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AuthSessionOutput
    {
        if (!$data instanceof AuthRefreshInput) {
            throw new \InvalidArgumentException('AuthRefreshInput expected.');
        }

        $now = new \DateTimeImmutable();
        $token = $this->refreshTokenManager->findByRawToken($data->refreshToken);

        if (null === $token) {
            $this->logger->warning('security.refresh_token.unknown');
            throw new UnauthorizedHttpException('Bearer', 'AUTH_REFRESH_TOKEN_INVALID');
        }

        if ($token->wasConsumedByRotation()) {
            // Replay of a spent token: someone (owner or thief) holds a stale
            // copy — revoke the whole rotation family and force re-login.
            $revoked = $this->refreshTokenManager->revokeFamily($token->getFamilyId(), $now);
            $this->entityManager->flush();
            $this->logger->warning('security.refresh_token.reuse_detected', [
                'user_id' => $token->getUser()->getId()->toRfc4122(),
                'family_id' => $token->getFamilyId()->toRfc4122(),
                'revoked_count' => $revoked,
            ]);
            throw new UnauthorizedHttpException('Bearer', 'AUTH_REFRESH_TOKEN_REUSED');
        }

        if ($token->isRevoked() || $token->isExpired($now)) {
            $this->logger->warning('security.refresh_token.invalid', [
                'user_id' => $token->getUser()->getId()->toRfc4122(),
            ]);
            throw new UnauthorizedHttpException('Bearer', 'AUTH_REFRESH_TOKEN_INVALID');
        }

        $user = $token->getUser();
        if (null !== $user->getDeletedAt() || !$user->isActive()) {
            // Hardening required by the issue: a suspended or soft-deleted
            // account must not mint new tokens. Defensive cleanup of any
            // remaining active tokens for the account.
            $this->refreshTokenManager->revokeAllForUser($user, $now);
            $this->entityManager->flush();
            $this->logger->warning('security.refresh_token.account_disabled', [
                'user_id' => $user->getId()->toRfc4122(),
            ]);
            throw new UnauthorizedHttpException('Bearer', 'AUTH_ACCOUNT_DISABLED');
        }

        $token->consumeForRotation($now);
        $newRawToken = $this->refreshTokenManager->issue(
            $user,
            familyId: $token->getFamilyId(),
            deviceLabel: $token->getDeviceLabel(),
            createdByIp: $this->requestStack->getCurrentRequest()?->getClientIp(),
            now: $now,
        );
        $this->entityManager->flush();

        $this->logger->info('security.refresh_token.rotated', [
            'user_id' => $user->getId()->toRfc4122(),
            'family_id' => $token->getFamilyId()->toRfc4122(),
        ]);

        return new AuthSessionOutput(
            token: $this->jwtTokenManager->create($user),
            refreshToken: $newRawToken,
            expiresIn: $this->jwtTokenTtl,
        );
    }
}
