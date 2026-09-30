<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Shop;
use App\Enum\MerchantMembershipStatus;
use App\Repository\MerchantInvitationTokenRepository;
use App\Repository\MerchantMembershipRepository;
use App\Repository\ShopRepository;
use App\Repository\UserRepository;
use App\Security\MerchantTeamManagementChecker;
use App\Service\AdminAuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Revokes a secondary team account (MERCHANT-TEAM-004).
 *
 * The membership becomes revoked (history preserved, User never deleted),
 * pending invitation tokens are invalidated, and access is denied on the very
 * next request through the membership-based checker. Repeating the call is
 * idempotent (204).
 *
 * @implements ProcessorInterface<mixed, null>
 */
final readonly class RevokeMerchantTeamAccountProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private UserRepository $userRepository,
        private MerchantMembershipRepository $membershipRepository,
        private MerchantInvitationTokenRepository $invitationTokenRepository,
        private MerchantTeamManagementChecker $teamManagementChecker,
        private AdminAuditLogger $auditLogger,
        private EntityManagerInterface $entityManager,
        #[Autowire(service: 'monolog.logger.admin')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $shop = $this->resolveShop($uriVariables);
        ['organization' => $organization, 'actor' => $actor] = $this->teamManagementChecker->denyUnlessPrimaryAccountOfShop($shop);

        $accountId = (string) ($uriVariables['accountId'] ?? '');
        if (!Uuid::isValid($accountId)) {
            throw new NotFoundHttpException('MERCHANT_ACCOUNT_NOT_FOUND');
        }
        $target = $this->userRepository->find($accountId);
        $membership = null === $target
            ? null
            : $this->membershipRepository->findOneByOrganizationAndUser($organization, $target);
        if (null === $target || null === $membership) {
            throw new NotFoundHttpException('MERCHANT_ACCOUNT_NOT_FOUND');
        }

        if (true === $organization->getPrimaryAccount()?->getId()->equals($target->getId())) {
            throw new UnprocessableEntityHttpException('MERCHANT_PRIMARY_ACCOUNT_CANNOT_BE_REVOKED');
        }
        if ($actor->getId()->equals($target->getId())) {
            throw new UnprocessableEntityHttpException('MERCHANT_ACCOUNT_SELF_REVOCATION_FORBIDDEN');
        }

        if (MerchantMembershipStatus::Revoked === $membership->getStatus()) {
            // Idempotent: repeating the revocation changes nothing.
            return null;
        }

        $previousStatus = $membership->getStatus()->value;
        $membership->revoke($actor);
        // An unused invitation link must not outlive the revocation.
        $this->invitationTokenRepository->revokePendingInvitationsForMerchant($target, new \DateTimeImmutable());

        $this->auditLogger->log(
            action: 'merchant.account.revoke',
            resourceType: 'merchant_organization',
            resourceId: $organization->getId()->toRfc4122(),
            summary: \sprintf('Compte %s révoqué.', $target->getEmail()),
            metadata: [
                'store_id' => $shop->getId()->toRfc4122(),
                'target_user_id' => $target->getId()->toRfc4122(),
                'previous_status' => $previousStatus,
                'new_status' => MerchantMembershipStatus::Revoked->value,
            ],
        );
        $this->entityManager->flush();

        $this->logger->info('merchant.team.account_revoked', [
            'organization_id' => $organization->getId()->toRfc4122(),
            'actor_user_id' => $actor->getId()->toRfc4122(),
            'target_user_id' => $target->getId()->toRfc4122(),
        ]);

        return null;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function resolveShop(array $uriVariables): Shop
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('MERCHANT_STORE_NOT_FOUND');
        }
        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('MERCHANT_STORE_NOT_FOUND');
        }

        return $shop;
    }
}
