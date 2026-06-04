<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminStoreActivationChecklistOutput;
use App\Repository\AdminStoreRepository;
use App\Service\StoreActivationChecklistCalculator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<AdminStoreActivationChecklistOutput>
 */
final readonly class AdminStoreActivationChecklistProvider implements ProviderInterface
{
    public function __construct(
        private AdminStoreRepository $adminStoreRepository,
        private StoreActivationChecklistCalculator $calculator,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminStoreActivationChecklistOutput
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('ADMIN_STORE_NOT_FOUND');
        }

        $shop = $this->adminStoreRepository->findOne($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('ADMIN_STORE_NOT_FOUND');
        }

        return $this->calculator->calculate($shop);
    }
}
