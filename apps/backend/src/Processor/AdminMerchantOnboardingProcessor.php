<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminCatalogPreloadErrorOutput;
use App\ApiResource\AdminCatalogPreloadOutput;
use App\ApiResource\AdminMerchantOnboardingFirstLoginOutput;
use App\ApiResource\AdminMerchantOnboardingOutput;
use App\ApiResource\AdminStoreOutputFactory;
use App\Dto\AdminMerchantOnboardingInput;
use App\Entity\Shop;
use App\Entity\User;
use App\Provider\AdminMerchantItemProvider;
use App\Repository\AdminMerchantRepository;
use App\Repository\AdminStoreRepository;
use App\Repository\UserRepository;
use App\Service\AdminAuditLogger;
use App\Service\MerchantInvitationSenderInterface;
use App\Service\MerchantInvitationTokenManager;
use App\Service\MerchantOperationalJournalCalculator;
use App\Service\MerchantTemporaryPasswordManager;
use App\Service\ProductGroupCatalogImporter;
use App\Service\ProductGroupCatalogImportResult;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<AdminMerchantOnboardingInput, AdminMerchantOnboardingOutput>
 */
final readonly class AdminMerchantOnboardingProcessor implements ProcessorInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private AdminMerchantRepository $adminMerchantRepository,
        private AdminStoreRepository $adminStoreRepository,
        private AdminStoreOutputFactory $adminStoreOutputFactory,
        private EntityManagerInterface $entityManager,
        private AdminAuditLogger $auditLogger,
        private MerchantTemporaryPasswordManager $temporaryPasswordManager,
        private MerchantInvitationTokenManager $invitationTokenManager,
        private MerchantInvitationSenderInterface $invitationSender,
        private UserPasswordHasherInterface $passwordHasher,
        private Security $security,
        private MerchantOperationalJournalCalculator $operationalJournalCalculator,
        private ProductGroupCatalogImporter $productGroupCatalogImporter,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminMerchantOnboardingOutput
    {
        if (!$data instanceof AdminMerchantOnboardingInput) {
            throw new \InvalidArgumentException('AdminMerchantOnboardingInput expected.');
        }

        if (null === $data->merchant || null === $data->shop) {
            throw new UnprocessableEntityHttpException('ADMIN_MERCHANT_ONBOARDING_PAYLOAD_INVALID');
        }

        $email = strtolower(trim($data->merchant->email));
        if (null !== $this->userRepository->findOneBy(['email' => $email])) {
            throw new UnprocessableEntityHttpException('ADMIN_MERCHANT_EMAIL_ALREADY_EXISTS');
        }

        $firstName = $this->normalizeRequiredString($data->merchant->firstName, 'ADMIN_MERCHANT_FIRST_NAME_BLANK');
        $lastName = $this->normalizeRequiredString($data->merchant->lastName, 'ADMIN_MERCHANT_LAST_NAME_BLANK');
        $merchant = (new User())
            ->setEmail($email)
            ->setRoles(['ROLE_MERCHANT'])
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setName($firstName.' '.$lastName)
            ->setPhone($this->normalizeNullableString($data->merchant->phone))
            ->setActive(true);

        $temporaryPassword = null;
        $invitationRawToken = null;
        $invitationExpiresAt = null;

        $shopName = $this->normalizeRequiredString($data->shop->name, 'ADMIN_STORE_NAME_BLANK');

        $shop = (new Shop())
            ->setName($shopName)
            ->setSlug($this->generateUniqueSlug($shopName))
            ->setAddress($this->normalizeNullableString($data->shop->address))
            ->setCity($this->normalizeNullableString($data->shop->city))
            ->setPhone($this->normalizeNullableString($data->shop->phone))
            ->setQrCodeToken(Uuid::v4()->toRfc4122())
            ->setOwner($merchant)
            ->setActive(true);

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            if ('email_invitation' === $data->firstLoginMode) {
                $this->initializeEmailInvitation($merchant);
                $createdInvitation = $this->invitationTokenManager->createForMerchant($merchant, $this->currentAdmin());
                $invitationRawToken = $createdInvitation['rawToken'];
                $invitationToken = $createdInvitation['token'];
                $invitationExpiresAt = $invitationToken->getExpiresAt();
            } else {
                $temporaryPassword = $this->temporaryPasswordManager->generateFor($merchant);
            }

            $this->entityManager->persist($merchant);
            $this->entityManager->persist($shop);
            $this->auditCoreCreation($merchant, $shop);
            if ('email_invitation' === $data->firstLoginMode) {
                $this->auditInvitationCreation($merchant, $invitationExpiresAt);
            } else {
                $this->auditTemporaryPasswordCreation($merchant);
            }
            $this->entityManager->flush();

            $catalogPreload = $this->productGroupCatalogImporter->importGroupsForAdmin($shop, $data->productGroupIds);
            if ([] !== $data->productGroupIds) {
                $this->auditCatalogPreload($shop, $catalogPreload);
                $this->entityManager->flush();
            }

            $connection->commit();
        } catch (UniqueConstraintViolationException) {
            $connection->rollBack();
            throw new UnprocessableEntityHttpException('ADMIN_MERCHANT_EMAIL_ALREADY_EXISTS');
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        if ('email_invitation' === $data->firstLoginMode) {
            if (null === $invitationRawToken || null === $invitationExpiresAt) {
                throw new \LogicException('Merchant invitation token was not created.');
            }
            $this->invitationSender->send($merchant, $invitationRawToken, $invitationExpiresAt);
        }

        return new AdminMerchantOnboardingOutput(
            id: $merchant->getId()->toRfc4122(),
            merchant: AdminMerchantItemProvider::toOutput(
                $merchant,
                $this->adminMerchantRepository->countStores($merchant),
                opsJournal: $this->operationalJournalCalculator->calculate($merchant),
            ),
            shop: $this->adminStoreOutputFactory->create(
                shop: $shop,
                productsCount: $catalogPreload->created,
            ),
            firstLogin: new AdminMerchantOnboardingFirstLoginOutput(
                mode: $data->firstLoginMode,
                temporaryPassword: $temporaryPassword,
                expiresAt: ('email_invitation' === $data->firstLoginMode ? $invitationExpiresAt : $merchant->getTemporaryPasswordExpiresAt())
                    ?->format(\DateTimeInterface::ATOM),
                invitationStatus: 'email_invitation' === $data->firstLoginMode ? 'sent' : null,
            ),
            catalogPreload: $this->catalogPreloadOutput($catalogPreload),
        );
    }

    private function initializeEmailInvitation(User $merchant): void
    {
        $serverOnlyPassword = bin2hex(random_bytes(32));
        $merchant
            ->setPassword($this->passwordHasher->hashPassword($merchant, $serverOnlyPassword))
            ->setPasswordChangeRequired(true)
            ->clearTemporaryPasswordWindow();
    }

    private function auditCoreCreation(User $merchant, Shop $shop): void
    {
        $this->auditLogger->log(
            action: 'merchant.create',
            resourceType: 'merchant',
            resourceId: $merchant->getId()->toRfc4122(),
            summary: \sprintf('Compte marchand %s créé pendant l’onboarding admin.', $merchant->getEmail()),
            metadata: ['email' => $merchant->getEmail()],
        );
        $this->auditLogger->log(
            action: 'shop.create',
            resourceType: 'shop',
            resourceId: $shop->getId()->toRfc4122(),
            summary: \sprintf('Supérette "%s" créée pendant l’onboarding admin.', $shop->getName()),
            metadata: ['name' => $shop->getName(), 'merchant_id' => $merchant->getId()->toRfc4122()],
        );
        $this->auditLogger->log(
            action: 'merchant.owner.attach',
            resourceType: 'shop',
            resourceId: $shop->getId()->toRfc4122(),
            summary: \sprintf('Supérette "%s" rattachée au marchand %s.', $shop->getName(), $merchant->getEmail()),
            metadata: ['merchant_id' => $merchant->getId()->toRfc4122(), 'shop_id' => $shop->getId()->toRfc4122()],
        );
    }

    private function auditTemporaryPasswordCreation(User $merchant): void
    {
        $this->auditLogger->log(
            action: 'merchant.temporary_password.create',
            resourceType: 'merchant',
            resourceId: $merchant->getId()->toRfc4122(),
            summary: \sprintf('Mot de passe temporaire créé pour le marchand %s.', $merchant->getEmail()),
            metadata: [
                'email' => $merchant->getEmail(),
                'temporary_password_expires_at' => $merchant->getTemporaryPasswordExpiresAt()?->format(\DateTimeInterface::ATOM),
            ],
        );
    }

    private function auditInvitationCreation(User $merchant, ?\DateTimeImmutable $expiresAt): void
    {
        $this->auditLogger->log(
            action: 'merchant.invitation.create',
            resourceType: 'merchant',
            resourceId: $merchant->getId()->toRfc4122(),
            summary: \sprintf('Invitation email marchand %s créée pendant l’onboarding admin.', $merchant->getEmail()),
            metadata: [
                'email' => $merchant->getEmail(),
                'expires_at' => $expiresAt?->format(\DateTimeInterface::ATOM),
                'source' => 'admin_merchant_onboarding',
            ],
        );
    }

    private function currentAdmin(): ?User
    {
        $admin = $this->security->getUser();

        return $admin instanceof User ? $admin : null;
    }

    private function auditCatalogPreload(Shop $shop, ProductGroupCatalogImportResult $result): void
    {
        $this->auditLogger->log(
            action: 'catalog.preload.apply',
            resourceType: 'shop',
            resourceId: $shop->getId()->toRfc4122(),
            summary: \sprintf('Préchargement catalogue appliqué à la supérette "%s".', $shop->getName()),
            metadata: [
                'added_count' => $result->created,
                'already_existing_count' => $result->alreadyInCatalog,
                'ignored_count' => $result->skipped,
            ],
        );
    }

    private function catalogPreloadOutput(ProductGroupCatalogImportResult $result): AdminCatalogPreloadOutput
    {
        return new AdminCatalogPreloadOutput(
            addedCount: $result->created,
            alreadyExistingCount: $result->alreadyInCatalog,
            ignoredCount: $result->skipped,
            errors: array_map(
                static fn ($error): AdminCatalogPreloadErrorOutput => new AdminCatalogPreloadErrorOutput(
                    productReferenceId: $error->productReferenceId,
                    code: $error->code,
                    message: $error->message,
                    productGroupId: $error->productGroupId,
                ),
                $result->errors,
            ),
        );
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = $this->slugify($name);
        $slug = $base;
        $suffix = 2;

        while (null !== $this->adminStoreRepository->findOneBySlug($slug)) {
            $suffixText = '-'.$suffix;
            $slug = mb_substr($base, 0, 180 - mb_strlen($suffixText)).$suffixText;
            ++$suffix;
        }

        return $slug;
    }

    private function slugify(string $name): string
    {
        $slug = (new AsciiSlugger('fr'))->slug($name)->lower()->toString();

        return '' === $slug ? 'store' : mb_substr($slug, 0, 180);
    }

    private function normalizeRequiredString(string $value, string $errorCode): string
    {
        $normalized = trim($value);
        if ('' === $normalized) {
            throw new UnprocessableEntityHttpException($errorCode);
        }

        return $normalized;
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
