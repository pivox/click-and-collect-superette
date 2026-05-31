<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\MerchantCatalogUpdateInput;
use App\Entity\User;
use App\Enum\MerchantProductPriceChangeType;
use App\Enum\MerchantProductPriceSource;
use App\Repository\MerchantCategoryRepository;
use App\Repository\MerchantProductRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\MerchantProductPriceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<MerchantCatalogUpdateInput, void>
 */
final readonly class UpdateMerchantCatalogProductProcessor implements ProcessorInterface
{
    public function __construct(
        private MerchantProductRepository $merchantProductRepository,
        private MerchantCategoryRepository $merchantCategoryRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private MerchantProductPriceService $priceService,
        private EntityManagerInterface $entityManager,
        private Security $security,
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
            $user = $this->security->getUser();
            $this->priceService->changePrice(
                merchantProduct: $merchantProduct,
                newPrice: $data->priceTnd,
                changeType: MerchantProductPriceChangeType::ManualUpdate,
                source: MerchantProductPriceSource::MerchantDashboard,
                changedByUser: $user instanceof User ? $user : null,
            );
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
        if ($data->hasMerchantCategoryId()) {
            $merchantCategoryId = $data->getMerchantCategoryId();
            if (null === $merchantCategoryId) {
                $merchantProduct->setMerchantCategory(null);
            } else {
                $merchantCategory = $this->merchantCategoryRepository->find($merchantCategoryId);
                if (null === $merchantCategory) {
                    throw new NotFoundHttpException('MERCHANT_CATEGORY_NOT_FOUND');
                }
                if (!$merchantCategory->getShop()->getId()->equals($merchantProduct->getShop()->getId())) {
                    throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'MERCHANT_CATEGORY_SHOP_INVALID');
                }
                if (!$merchantCategory->isActive()) {
                    throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'MERCHANT_CATEGORY_INACTIVE');
                }

                $merchantProduct->setMerchantCategory($merchantCategory);
            }
        }

        $this->entityManager->flush();
    }
}
