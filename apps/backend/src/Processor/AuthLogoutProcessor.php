<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\AuthLogoutInput;
use App\Entity\User;
use App\Service\RefreshTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Logout (#616): revokes the presented refresh token, or every active token
 * of the user with `all: true`. Idempotent, always 204 — an unknown token or
 * one belonging to another user is silently ignored (no enumeration). The
 * current access JWT stays valid until natural expiry (stateless trade-off,
 * <= 1 h, documented in the API contract).
 *
 * @implements ProcessorInterface<AuthLogoutInput, null>
 */
final readonly class AuthLogoutProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private RefreshTokenManager $refreshTokenManager,
        private EntityManagerInterface $entityManager,
        #[Autowire(service: 'monolog.logger.security')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        if (!$data instanceof AuthLogoutInput) {
            throw new \InvalidArgumentException('AuthLogoutInput expected.');
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('AUTH_REQUIRED');
        }

        $now = new \DateTimeImmutable();

        if ($data->all) {
            $revoked = $this->refreshTokenManager->revokeAllForUser($user, $now);
            $this->entityManager->flush();
            $this->logger->info('security.logout.all_devices', [
                'user_id' => $user->getId()->toRfc4122(),
                'revoked_count' => $revoked,
            ]);

            return null;
        }

        if (null !== $data->refreshToken) {
            $token = $this->refreshTokenManager->findByRawToken($data->refreshToken);
            // Ownership check: never let a user revoke another user's token,
            // and never reveal whether the token exists.
            if (null !== $token && $token->getUser()->getId()->equals($user->getId())) {
                $token->revoke($now);
                $this->entityManager->flush();
                $this->logger->info('security.logout.token_revoked', [
                    'user_id' => $user->getId()->toRfc4122(),
                ]);
            }
        }

        return null;
    }
}
