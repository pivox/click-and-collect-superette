<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminMerchantOutput;
use App\Dto\AdminUpdateMerchantInput;
use App\Entity\User;
use App\Provider\AdminMerchantItemProvider;
use App\Repository\AdminMerchantRepository;
use App\Service\AdminAuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<AdminUpdateMerchantInput, AdminMerchantOutput>
 */
final readonly class AdminUpdateMerchantProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminMerchantRepository $adminMerchantRepository,
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
        private AdminAuditLogger $auditLogger,
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
        if (!$data instanceof AdminUpdateMerchantInput) {
            throw new \InvalidArgumentException('AdminUpdateMerchantInput expected.');
        }

        $merchantId = (string) ($uriVariables['merchantId'] ?? '');
        $merchant = $this->resolveMerchant($merchantId);

        // Only update fields that were explicitly provided in the request body
        $payload = $this->currentPayload();

        $knownFields = ['first_name', 'last_name', 'phone', 'is_active'];
        $updatedFields = array_values(array_intersect(array_keys($payload), $knownFields));

        $this->logger->debug('admin.merchant_update.start', [
            'merchant_id' => $merchantId,
            'updated_fields' => $updatedFields,
        ]);

        if (\array_key_exists('first_name', $payload) && null !== $data->firstName) {
            $merchant->setFirstName($data->firstName);
        }

        if (\array_key_exists('last_name', $payload) && null !== $data->lastName) {
            $merchant->setLastName($data->lastName);
        }

        if (\array_key_exists('phone', $payload)) {
            $merchant->setPhone($data->phone);
        }

        if (\array_key_exists('is_active', $payload) && null !== $data->isActive) {
            $merchant->setActive($data->isActive);
        }

        // Rebuild the display name when first or last name changed
        $firstName = $merchant->getFirstName();
        $lastName = $merchant->getLastName();
        if (null !== $firstName && null !== $lastName) {
            $merchant->setName($firstName.' '.$lastName);
        }

        try {
            $this->auditLogger->log(
                action: 'merchant.update',
                resourceType: 'merchant',
                resourceId: $merchant->getId()->toRfc4122(),
                summary: \sprintf('Compte marchand %s modifié.', $merchant->getEmail()),
                metadata: ['email' => $merchant->getEmail()],
            );
            $this->entityManager->flush();
            $this->logger->info('merchant.updated', [
                'merchant_id' => $merchantId,
                'updated_fields' => $updatedFields,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('admin.merchant_update.failed', [
                'merchant_id' => $merchantId,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return AdminMerchantItemProvider::toOutput($merchant, $this->adminMerchantRepository->countStores($merchant));
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
