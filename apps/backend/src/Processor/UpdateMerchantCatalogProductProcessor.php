<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\MerchantCatalogUpdateInput;
use App\Repository\MerchantProductRepository;
use App\Security\MerchantShopAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<MerchantCatalogUpdateInput, void>
 */
final readonly class UpdateMerchantCatalogProductProcessor implements ProcessorInterface
{
    public function __construct(
        private MerchantProductRepository $merchantProductRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof MerchantCatalogUpdateInput) {
            throw new \InvalidArgumentException('MerchantCatalogUpdateInput expected.');
        }

        $merchantProductId = (string) ($uriVariables['merchantProductId'] ?? '');
        if (!Uuid::isValid($merchantProductId)) {
            throw new NotFoundHttpException('MERCHANT_PRODUCT_NOT_FOUND');
        }

        $merchantProduct = $this->merchantProductRepository->find($merchantProductId);
        if (null === $merchantProduct) {
            throw new NotFoundHttpException('MERCHANT_PRODUCT_NOT_FOUND');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($merchantProduct->getShop());

        if (null !== $data->priceTnd) {
            $merchantProduct->setPriceTnd($data->priceTnd);
        }
        if (null !== $data->isAvailable) {
            $merchantProduct->setAvailable($data->isAvailable);
        }
        if (null !== $data->isVisible) {
            $merchantProduct->setVisible($data->isVisible);
        }
        if ($data->hasMerchantNote()) {
            $merchantProduct->setMerchantNote($data->getMerchantNote());
        }

        $this->entityManager->flush();
    }
}
