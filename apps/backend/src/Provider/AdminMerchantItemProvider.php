<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminMerchantOutput;
use App\Entity\User;
use App\Repository\AdminMerchantRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<AdminMerchantOutput>
 */
final readonly class AdminMerchantItemProvider implements ProviderInterface
{
    public function __construct(
        private AdminMerchantRepository $adminMerchantRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminMerchantOutput
    {
        $merchantId = (string) ($uriVariables['merchantId'] ?? '');
        if (!Uuid::isValid($merchantId)) {
            throw new NotFoundHttpException('ADMIN_MERCHANT_NOT_FOUND');
        }

        $merchant = $this->adminMerchantRepository->findOne($merchantId);
        if (null === $merchant) {
            throw new NotFoundHttpException('ADMIN_MERCHANT_NOT_FOUND');
        }

        return self::toOutput($merchant, $this->adminMerchantRepository->countStores($merchant));
    }

    public static function toOutput(User $merchant, int $storesCount): AdminMerchantOutput
    {
        return new AdminMerchantOutput(
            id: $merchant->getId()->toRfc4122(),
            email: $merchant->getEmail(),
            firstName: $merchant->getFirstName(),
            lastName: $merchant->getLastName(),
            phone: $merchant->getPhone(),
            isActive: $merchant->isActive(),
            createdAt: $merchant->getCreatedAt()->format(\DateTimeInterface::ATOM),
            storesCount: $storesCount,
        );
    }
}
