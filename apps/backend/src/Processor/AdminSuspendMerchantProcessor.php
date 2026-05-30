<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminMerchantOutput;
use App\Entity\User;
use App\Provider\AdminMerchantItemProvider;
use App\Repository\AdminMerchantRepository;
use App\Service\AdminAuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, AdminMerchantOutput>
 */
final readonly class AdminSuspendMerchantProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminMerchantRepository $adminMerchantRepository,
        private EntityManagerInterface $entityManager,
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
        $merchantId = (string) ($uriVariables['merchantId'] ?? '');
        $merchant = $this->resolveMerchant($merchantId);

        $this->logger->debug('admin.merchant_suspend.start', ['merchant_id' => $merchantId]);

        if (!$merchant->isActive()) {
            $this->logger->warning('admin.merchant_suspend.already_inactive', ['merchant_id' => $merchantId]);
        }

        try {
            $merchant->setActive(false);
            $this->auditLogger->log(
                action: 'merchant.suspend',
                resourceType: 'merchant',
                resourceId: $merchant->getId()->toRfc4122(),
                summary: \sprintf('Compte marchand %s suspendu.', $merchant->getEmail()),
                metadata: ['email' => $merchant->getEmail()],
            );
            $this->entityManager->flush();
            $this->logger->info('merchant.suspended', [
                'merchant_id' => $merchantId,
                'email' => $merchant->getEmail(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('admin.merchant_suspend.failed', [
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
}
