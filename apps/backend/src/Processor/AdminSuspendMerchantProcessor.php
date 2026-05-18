<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminMerchantOutput;
use App\Entity\User;
use App\Provider\AdminMerchantItemProvider;
use App\Repository\AdminMerchantRepository;
use Doctrine\ORM\EntityManagerInterface;
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

        $merchant->setActive(false);
        $this->entityManager->flush();

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
