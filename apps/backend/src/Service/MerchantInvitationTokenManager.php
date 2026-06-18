<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\MerchantInvitationToken;
use App\Entity\User;
use App\Repository\MerchantInvitationTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class MerchantInvitationTokenManager
{
    public function __construct(
        private MerchantInvitationTokenRepository $tokenRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private int $merchantInvitationTokenTtl,
    ) {
    }

    /**
     * @return array{rawToken: string, token: MerchantInvitationToken}
     */
    public function createForMerchant(User $merchant, ?User $createdBy = null): array
    {
        $now = new \DateTimeImmutable();
        $this->tokenRepository->revokePendingInvitationsForMerchant($merchant, $now);

        $rawToken = self::generateRawToken();
        $token = new MerchantInvitationToken(
            merchant: $merchant,
            tokenHash: self::hashToken($rawToken),
            expiresAt: $now->modify(\sprintf('+%d seconds', $this->merchantInvitationTokenTtl)),
            createdBy: $createdBy,
        );

        $this->entityManager->persist($token);

        return ['rawToken' => $rawToken, 'token' => $token];
    }

    public function verify(string $rawToken): MerchantInvitationToken
    {
        $token = $this->tokenRepository->findOneByHash(self::hashToken($rawToken));
        if (!$token instanceof MerchantInvitationToken) {
            throw new BadRequestHttpException('MERCHANT_INVITATION_TOKEN_INVALID');
        }

        if ($token->isUsed()) {
            throw new BadRequestHttpException('MERCHANT_INVITATION_TOKEN_ALREADY_USED');
        }

        if ($token->isRevoked()) {
            throw new BadRequestHttpException('MERCHANT_INVITATION_TOKEN_REVOKED');
        }

        if ($token->isExpired()) {
            throw new BadRequestHttpException('MERCHANT_INVITATION_TOKEN_EXPIRED');
        }

        $merchant = $token->getMerchant();
        if (
            !\in_array('ROLE_MERCHANT', $merchant->getRoles(), true)
            || !$merchant->isActive()
            || null !== $merchant->getDeletedAt()
        ) {
            throw new BadRequestHttpException('MERCHANT_INVITATION_TOKEN_INVALID');
        }

        return $token;
    }

    public function complete(string $rawToken, string $newPassword): MerchantInvitationToken
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $token = $this->verify($rawToken);
            $now = new \DateTimeImmutable();

            $updatedRows = $connection->executeStatement(
                'UPDATE merchant_invitation_tokens SET used_at = :usedAt WHERE id = :id AND used_at IS NULL AND revoked_at IS NULL AND expires_at > :now',
                [
                    'usedAt' => $now,
                    'id' => $token->getId(),
                    'now' => $now,
                ],
                [
                    'usedAt' => 'datetime_immutable',
                    'id' => 'uuid',
                    'now' => 'datetime_immutable',
                ],
            );

            if (1 !== $updatedRows) {
                throw new BadRequestHttpException('MERCHANT_INVITATION_TOKEN_ALREADY_USED');
            }

            $merchant = $token->getMerchant();
            $merchant
                ->setPassword($this->passwordHasher->hashPassword($merchant, $newPassword))
                ->setPasswordChangeRequired(false);
            $token->markUsed($now);
            $this->entityManager->flush();
            $connection->commit();

            return $token;
        } catch (\Throwable $e) {
            $connection->rollBack();

            throw $e;
        }
    }

    public static function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    private static function generateRawToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
