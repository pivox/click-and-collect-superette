<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantTeamAccountOutput;
use App\Dto\MerchantTeamInvitationInput;
use App\Entity\MerchantMembership;
use App\Entity\User;
use App\Enum\MerchantMembershipStatus;
use App\Repository\MerchantMembershipRepository;
use App\Repository\ShopRepository;
use App\Repository\UserRepository;
use App\Security\MerchantTeamManagementChecker;
use App\Service\AdminAuditLogger;
use App\Service\MerchantInvitationSenderInterface;
use App\Service\MerchantInvitationTokenManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Invites a secondary account into the shop organization (MERCHANT-TEAM-004).
 *
 * Creates the User + invited membership + invitation token transactionally,
 * then sends the email best-effort: a delivery failure keeps the invitation
 * (resend available) and is exposed through a non-sensitive status. No
 * subscription, CRM profile, billing document or shop is ever created here,
 * and no password is returned.
 *
 * @implements ProcessorInterface<MerchantTeamInvitationInput, MerchantTeamAccountOutput>
 */
final readonly class InviteMerchantTeamAccountProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private UserRepository $userRepository,
        private MerchantMembershipRepository $membershipRepository,
        private MerchantTeamManagementChecker $teamManagementChecker,
        private MerchantInvitationTokenManager $invitationTokenManager,
        private MerchantInvitationSenderInterface $invitationSender,
        private UserPasswordHasherInterface $passwordHasher,
        private AdminAuditLogger $auditLogger,
        private EntityManagerInterface $entityManager,
        private int $merchantTeamAccountLimit,
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
        if (!$data instanceof MerchantTeamInvitationInput) {
            throw new \InvalidArgumentException('MerchantTeamInvitationInput expected.');
        }

        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('MERCHANT_STORE_NOT_FOUND');
        }
        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('MERCHANT_STORE_NOT_FOUND');
        }

        ['organization' => $organization, 'actor' => $actor] = $this->teamManagementChecker->denyUnlessPrimaryAccountOfShop($shop);

        $email = strtolower(trim((string) $data->email));
        if (null !== $this->userRepository->findOneBy(['email' => $email])) {
            // A used email is never attached automatically to the organization.
            throw new UnprocessableEntityHttpException('MERCHANT_ACCOUNT_EMAIL_ALREADY_USED');
        }

        $this->denyIfQuotaReached($organization->getId()->toRfc4122());

        $firstName = trim((string) $data->firstName);
        $lastName = trim((string) $data->lastName);
        $invitee = (new User())
            ->setEmail($email)
            ->setRoles(['ROLE_MERCHANT'])
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setName(trim($firstName.' '.$lastName))
            ->setPhone($this->normalizeNullableString($data->phone))
            ->setActive(true)
            ->setPasswordChangeRequired(true);
        // Server-only random password until the invitee defines their own.
        $invitee->setPassword($this->passwordHasher->hashPassword($invitee, bin2hex(random_bytes(32))));
        $invitee->clearTemporaryPasswordWindow();

        $membership = (new MerchantMembership())
            ->setOrganization($organization)
            ->setUser($invitee)
            ->markInvited($actor);

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $this->entityManager->persist($invitee);
            $this->entityManager->persist($membership);

            $created = $this->invitationTokenManager->createForMerchant($invitee, $actor);
            $token = $created['token'];

            $this->auditLogger->log(
                action: 'merchant.account.invite',
                resourceType: 'merchant_organization',
                resourceId: $organization->getId()->toRfc4122(),
                summary: \sprintf('Compte secondaire %s invité.', $email),
                metadata: [
                    'store_id' => $shop->getId()->toRfc4122(),
                    'target_user_email' => $email,
                    'expires_at' => $token->getExpiresAt()->format(\DateTimeInterface::ATOM),
                ],
            );

            $this->entityManager->flush();

            // Quota re-check inside the transaction guards concurrent creations.
            $this->denyIfQuotaReached($organization->getId()->toRfc4122(), afterInsert: true);

            $connection->commit();
        } catch (UniqueConstraintViolationException) {
            $connection->rollBack();
            throw new UnprocessableEntityHttpException('MERCHANT_ACCOUNT_EMAIL_ALREADY_USED');
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        $invitationStatus = 'sent';
        try {
            $this->invitationSender->send($invitee, $created['rawToken'], $token->getExpiresAt());
        } catch (\Throwable $e) {
            $invitationStatus = 'delivery_failed';
            $this->logger->error('merchant.team.invitation_delivery_failed', [
                'organization_id' => $organization->getId()->toRfc4122(),
                'target_user_id' => $invitee->getId()->toRfc4122(),
                'exception_class' => $e::class,
            ]);
        }

        $this->logger->info('merchant.team.account_invited', [
            'organization_id' => $organization->getId()->toRfc4122(),
            'actor_user_id' => $actor->getId()->toRfc4122(),
            'target_user_id' => $invitee->getId()->toRfc4122(),
            'invitation_status' => $invitationStatus,
        ]);

        return MerchantTeamAccountOutput::fromMembership($membership, $invitationStatus);
    }

    /**
     * Before insertion ($afterInsert = false) the quota is full once the count
     * reaches the limit, so refuse on ">=" as a fast path without writing.
     * After insertion ($afterInsert = true) the new membership is part of the
     * count, so only a count strictly above the limit reveals a concurrent
     * creation and triggers the rollback.
     */
    private function denyIfQuotaReached(string $organizationId, bool $afterInsert = false): void
    {
        $memberships = $this->membershipRepository->createQueryBuilder('m')
            ->andWhere('IDENTITY(m.organization) = :organizationId')
            ->andWhere('m.status != :revoked')
            ->setParameter('organizationId', $organizationId, 'uuid')
            ->setParameter('revoked', MerchantMembershipStatus::Revoked)
            ->getQuery()
            ->getResult();

        $count = \count((array) $memberships);
        $quotaReached = $afterInsert
            ? $count > $this->merchantTeamAccountLimit
            : $count >= $this->merchantTeamAccountLimit;

        if ($quotaReached) {
            throw new UnprocessableEntityHttpException('MERCHANT_ACCOUNT_LIMIT_REACHED');
        }
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }
        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
