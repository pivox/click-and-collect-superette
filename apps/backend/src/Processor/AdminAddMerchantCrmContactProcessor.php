<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminMerchantCrmOutput;
use App\ApiResource\AdminMerchantOutput;
use App\Dto\AdminMerchantCrmContactCreateInput;
use App\Entity\MerchantCrmContact;
use App\Entity\MerchantCrmProfile;
use App\Entity\User;
use App\Enum\MerchantCrmContactChannel;
use App\Provider\AdminMerchantItemProvider;
use App\Repository\AdminMerchantRepository;
use App\Repository\MerchantCrmContactRepository;
use App\Repository\MerchantCrmProfileRepository;
use App\Repository\SubscriptionRepository;
use App\Service\AdminAuditLogger;
use App\Service\MerchantOperationalJournalCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<AdminMerchantCrmContactCreateInput, AdminMerchantOutput>
 */
final readonly class AdminAddMerchantCrmContactProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminMerchantRepository $adminMerchantRepository,
        private SubscriptionRepository $subscriptionRepository,
        private MerchantCrmProfileRepository $crmProfileRepository,
        private MerchantCrmContactRepository $crmContactRepository,
        private EntityManagerInterface $entityManager,
        private Security $security,
        private AdminAuditLogger $auditLogger,
        private MerchantOperationalJournalCalculator $operationalJournalCalculator,
        #[Autowire(service: 'monolog.logger.admin')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminMerchantOutput
    {
        if (!$data instanceof AdminMerchantCrmContactCreateInput) {
            throw new \InvalidArgumentException('AdminMerchantCrmContactCreateInput expected.');
        }

        $admin = $this->security->getUser();
        if (!$admin instanceof User) {
            throw new AccessDeniedHttpException('ADMIN_ACCESS_REQUIRED');
        }

        $merchantId = (string) ($uriVariables['merchantId'] ?? '');
        $merchant = $this->resolveMerchant($merchantId);

        $contactedAt = $this->parseContactedAt($data->contactedAt);

        $profile = $this->crmProfileRepository->findOneByMerchant($merchant);
        if (null === $profile) {
            $profile = new MerchantCrmProfile($merchant);
            $this->entityManager->persist($profile);
        }

        $contact = new MerchantCrmContact(
            $merchant,
            $admin,
            MerchantCrmContactChannel::from((string) $data->channel),
            (string) $data->note,
            $contactedAt,
        );
        $this->entityManager->persist($contact);
        $profile->registerContactAt($contact->getContactedAt());

        try {
            $this->auditLogger->log(
                action: 'merchant.crm_contact_add',
                resourceType: 'merchant',
                resourceId: $merchant->getId()->toRfc4122(),
                summary: \sprintf('Contact commercial ajouté pour le marchand %s.', $merchant->getEmail()),
                metadata: ['email' => $merchant->getEmail(), 'channel' => $contact->getChannel()->value],
            );
            $this->entityManager->flush();
            $this->logger->info('merchant.crm_contact_added', [
                'merchant_id' => $merchantId,
                'channel' => $contact->getChannel()->value,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('admin.merchant_crm_contact_add.failed', [
                'merchant_id' => $merchantId,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return AdminMerchantItemProvider::toOutput(
            $merchant,
            $this->adminMerchantRepository->countStores($merchant),
            subscriptionLifecycle: $this->subscriptionRepository->findOneByMerchant($merchant)?->getLifecycle()->value,
            opsJournal: $this->operationalJournalCalculator->calculate($merchant),
            crm: AdminMerchantCrmOutput::fromProfile($profile, $this->crmContactRepository->findByMerchant($merchant)),
        );
    }

    private function parseContactedAt(?string $raw): ?\DateTimeImmutable
    {
        if (null === $raw || '' === $raw) {
            return null;
        }

        try {
            return new \DateTimeImmutable($raw);
        } catch (\Exception) {
            throw new UnprocessableEntityHttpException('ADMIN_MERCHANT_CRM_INVALID_CONTACTED_AT');
        }
    }

    private function resolveMerchant(string $merchantId): User
    {
        if (!Uuid::isValid($merchantId)) {
            throw new NotFoundHttpException('ADMIN_MERCHANT_NOT_FOUND');
        }

        $merchant = $this->adminMerchantRepository->findOne($merchantId);
        if (null === $merchant) {
            throw new NotFoundHttpException('ADMIN_MERCHANT_NOT_FOUND');
        }

        return $merchant;
    }
}
