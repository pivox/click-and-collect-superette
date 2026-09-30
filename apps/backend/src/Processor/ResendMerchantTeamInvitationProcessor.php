<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantTeamAccountOutput;
use App\Entity\MerchantMembership;
use App\Entity\MerchantOrganization;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\MerchantMembershipStatus;
use App\Repository\MerchantInvitationTokenRepository;
use App\Repository\MerchantMembershipRepository;
use App\Repository\ShopRepository;
use App\Repository\UserRepository;
use App\Security\MerchantTeamManagementChecker;
use App\Service\AdminAuditLogger;
use App\Service\MerchantInvitationSenderInterface;
use App\Service\MerchantInvitationTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Resends the invitation of an invited team account with token rotation
 * (MERCHANT-TEAM-004). Previous pending tokens are revoked by the manager,
 * so an old link can never be replayed.
 *
 * @implements ProcessorInterface<mixed, MerchantTeamAccountOutput>
 */
final readonly class ResendMerchantTeamInvitationProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private UserRepository $userRepository,
        private MerchantMembershipRepository $membershipRepository,
        private MerchantInvitationTokenRepository $invitationTokenRepository,
        private MerchantTeamManagementChecker $teamManagementChecker,
        private MerchantInvitationTokenManager $invitationTokenManager,
        private MerchantInvitationSenderInterface $invitationSender,
        private AdminAuditLogger $auditLogger,
        private EntityManagerInterface $entityManager,
        private int $merchantTeamInvitationResendCooldown,
        #[Autowire(service: 'monolog.logger.admin')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantTeamAccountOutput
    {
        $shop = $this->resolveShop($uriVariables);
        // Authorization first: account resolution must not leak anything to a
        // caller who cannot manage this shop's team.
        ['organization' => $organization, 'actor' => $actor] = $this->teamManagementChecker->denyUnlessPrimaryAccountOfShop($shop);
        [$membership, $target] = $this->resolveMembership($organization, $uriVariables);

        if (MerchantMembershipStatus::Invited !== $membership->getStatus()) {
            throw new UnprocessableEntityHttpException('MERCHANT_INVITATION_NOT_PENDING');
        }

        // Lightweight anti-abuse: refuse resends inside the cooldown window.
        $latest = $this->invitationTokenRepository->findOneBy(['merchant' => $target], ['createdAt' => 'DESC']);
        if (null !== $latest
            && null === $latest->getUsedAt()
            && null === $latest->getRevokedAt()
            && $latest->getCreatedAt() > new \DateTimeImmutable(\sprintf('-%d seconds', $this->merchantTeamInvitationResendCooldown))
        ) {
            throw new TooManyRequestsHttpException(null, 'MERCHANT_INVITATION_RESEND_TOO_SOON');
        }

        // Rotation: the manager revokes every pending token before creating one.
        $created = $this->invitationTokenManager->createForMerchant($target, $actor);
        $token = $created['token'];

        $this->auditLogger->log(
            action: 'merchant.account.invitation_resend',
            resourceType: 'merchant_organization',
            resourceId: $organization->getId()->toRfc4122(),
            summary: \sprintf('Invitation du compte %s renvoyée.', $target->getEmail()),
            metadata: [
                'store_id' => $shop->getId()->toRfc4122(),
                'target_user_email' => $target->getEmail(),
                'expires_at' => $token->getExpiresAt()->format(\DateTimeInterface::ATOM),
            ],
        );
        $this->entityManager->flush();

        $invitationStatus = 'sent';
        try {
            $this->invitationSender->send($target, $created['rawToken'], $token->getExpiresAt());
        } catch (\Throwable $e) {
            $invitationStatus = 'delivery_failed';
            $this->logger->error('merchant.team.invitation_delivery_failed', [
                'organization_id' => $organization->getId()->toRfc4122(),
                'target_user_id' => $target->getId()->toRfc4122(),
                'exception_class' => $e::class,
            ]);
        }

        return MerchantTeamAccountOutput::fromMembership($membership, $invitationStatus);
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

    /**
     * @param array<string, mixed> $uriVariables
     *
     * @return array{0: MerchantMembership, 1: User}
     */
    private function resolveMembership(MerchantOrganization $organization, array $uriVariables): array
    {
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

        return [$membership, $target];
    }
}
