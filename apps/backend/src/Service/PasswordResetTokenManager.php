<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Repository\PasswordResetTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class PasswordResetTokenManager
{
    public function __construct(
        private PasswordResetTokenRepository $tokenRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private int $passwordResetTokenTtl,
    ) {
    }

    public function createForUser(User $user): string
    {
        $now = new \DateTimeImmutable();
        $this->tokenRepository->consumeActiveTokensForUser($user, $now);

        $rawToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $token = new PasswordResetToken(
            $user,
            self::hashToken($rawToken),
            $now->modify(\sprintf('+%d seconds', $this->passwordResetTokenTtl)),
        );

        $this->entityManager->persist($token);

        return $rawToken;
    }

    public function confirm(string $rawToken, string $newPassword): void
    {
        $token = $this->tokenRepository->findOneByHash(self::hashToken($rawToken));
        if (null === $token) {
            throw new BadRequestHttpException('AUTH_RESET_TOKEN_INVALID');
        }

        if ($token->isConsumed()) {
            throw new BadRequestHttpException('AUTH_RESET_TOKEN_ALREADY_USED');
        }

        if ($token->isExpired()) {
            throw new BadRequestHttpException('AUTH_RESET_TOKEN_EXPIRED');
        }

        $user = $token->getUser();
        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $token->consume();
    }

    public static function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }
}
