<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminMerchantCrmOutput;
use App\ApiResource\AdminMerchantOutput;
use App\Dto\AdminMerchantCrmUpdateInput;
use App\Entity\MerchantCrmProfile;
use App\Entity\User;
use App\Enum\MerchantCrmStatus;
use App\Provider\AdminMerchantItemProvider;
use App\Repository\AdminMerchantRepository;
use App\Repository\MerchantCrmContactRepository;
use App\Repository\MerchantCrmProfileRepository;
use App\Repository\SubscriptionRepository;
use App\Service\AdminAuditLogger;
use App\Service\MerchantOperationalJournalCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<AdminMerchantCrmUpdateInput, AdminMerchantOutput>
 */
final readonly class AdminUpdateMerchantCrmProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminMerchantRepository $adminMerchantRepository,
        private SubscriptionRepository $subscriptionRepository,
        private MerchantCrmProfileRepository $crmProfileRepository,
        private MerchantCrmContactRepository $crmContactRepository,
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
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
        if (!$data instanceof AdminMerchantCrmUpdateInput) {
            throw new \InvalidArgumentException('AdminMerchantCrmUpdateInput expected.');
        }

        $merchantId = (string) ($uriVariables['merchantId'] ?? '');
        $merchant = $this->resolveMerchant($merchantId);

        // Only update fields that were explicitly provided in the request body
        $payload = $this->currentPayload();

        $knownFields = ['commercial_owner', 'status', 'next_action_at', 'next_action_note', 'commercial_note'];
        $updatedFields = array_values(array_intersect(array_keys($payload), $knownFields));

        $profile = $this->crmProfileRepository->findOneByMerchant($merchant);
        if (null === $profile) {
            $profile = new MerchantCrmProfile($merchant);
            $this->entityManager->persist($profile);
        }

        if (\array_key_exists('commercial_owner', $payload)) {
            $profile->setCommercialOwner($data->commercialOwner);
        }

        if (\array_key_exists('status', $payload) && null !== $data->status) {
            $profile->setStatus(MerchantCrmStatus::from($data->status));
        }

        if (\array_key_exists('next_action_at', $payload)) {
            $profile->setNextActionAt($this->parseDate($data->nextActionAt, 'ADMIN_MERCHANT_CRM_INVALID_NEXT_ACTION_AT'));
        }

        if (\array_key_exists('next_action_note', $payload)) {
            $profile->setNextActionNote($data->nextActionNote);
        }

        if (\array_key_exists('commercial_note', $payload)) {
            $profile->setCommercialNote($data->commercialNote);
        }

        try {
            $this->auditLogger->log(
                action: 'merchant.crm_update',
                resourceType: 'merchant',
                resourceId: $merchant->getId()->toRfc4122(),
                summary: \sprintf('Suivi commercial du marchand %s modifié.', $merchant->getEmail()),
                metadata: ['email' => $merchant->getEmail(), 'updated_fields' => $updatedFields],
            );
            $this->entityManager->flush();
            $this->logger->info('merchant.crm_updated', [
                'merchant_id' => $merchantId,
                'updated_fields' => $updatedFields,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('admin.merchant_crm_update.failed', [
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

    private function parseDate(?string $raw, string $errorCode): ?\DateTimeImmutable
    {
        if (null === $raw || '' === $raw) {
            return null;
        }

        try {
            return new \DateTimeImmutable($raw);
        } catch (\Exception) {
            throw new UnprocessableEntityHttpException($errorCode);
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

    /**
     * @return array<string, mixed>
     */
    private function currentPayload(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return [];
        }

        $content = $request->getContent();
        if ('' === $content) {
            return [];
        }

        $payload = json_decode($content, true);

        return \is_array($payload) ? $payload : [];
    }
}
