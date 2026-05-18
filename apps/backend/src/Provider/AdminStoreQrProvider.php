<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminStoreQrOutput;
use App\ApiResource\AdminStoreQrOutputFactory;
use App\Repository\AdminStoreRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<AdminStoreQrOutput>
 */
final readonly class AdminStoreQrProvider implements ProviderInterface
{
    public function __construct(
        private AdminStoreRepository $adminStoreRepository,
        private AdminStoreQrOutputFactory $adminStoreQrOutputFactory,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminStoreQrOutput
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('ADMIN_STORE_NOT_FOUND');
        }

        $shop = $this->adminStoreRepository->findOne($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('ADMIN_STORE_NOT_FOUND');
        }

        return $this->adminStoreQrOutputFactory->create($shop);
    }
}
