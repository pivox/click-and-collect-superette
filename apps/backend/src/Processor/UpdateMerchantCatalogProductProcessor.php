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

        $promotionTouched = $data->hasPromotionPriceTnd() || $data->hasPromotionEndsOn();
        $resolvedPromotionPriceTnd = null;
        $resolvedPromotionEndsOnDate = null;
        if ($promotionTouched) {
            $resolvedPromotionPriceTnd = $data->hasPromotionPriceTnd()
                ? $data->getPromotionPriceTnd()
                : $merchantProduct->getPromotionPriceTnd();
            $resolvedPromotionEndsOn = $data->hasPromotionEndsOn()
                ? $data->getPromotionEndsOn()
                : $merchantProduct->getPromotionEndsOn()?->format('Y-m-d');

            if ((null === $resolvedPromotionPriceTnd) xor (null === $resolvedPromotionEndsOn)) {
                throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'PROMOTION_PAYLOAD_INCOMPLETE');
            }

            if (null !== $resolvedPromotionPriceTnd && null !== $resolvedPromotionEndsOn) {
                $resolvedPromotionEndsOnDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $resolvedPromotionEndsOn, new \DateTimeZone('Africa/Tunis'));
                if (false === $resolvedPromotionEndsOnDate) {
                    throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'PROMOTION_ENDS_ON_INVALID');
                }

                $finalPriceTnd = $data->priceTnd ?? $merchantProduct->getPriceTnd();
                if (bccomp($resolvedPromotionPriceTnd, $finalPriceTnd, 3) >= 0) {
                    throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'PROMOTION_PRICE_MUST_BE_LOWER_THAN_PRICE');
                }
            }
        }

        if (null !== $data->priceTnd) {
            $user = $this->security->getUser();
            try {
                if ($promotionTouched) {
                    $merchantProduct
                        ->setPromotionPriceTnd(null)
                        ->setPromotionEndsOn(null);
                }
                $this->priceService->changePrice(
                    merchantProduct: $merchantProduct,
                    newPrice: $data->priceTnd,
                    changeType: MerchantProductPriceChangeType::ManualUpdate,
                    source: MerchantProductPriceSource::MerchantDashboard,
                    changedByUser: $user instanceof User ? $user : null,
                );
            } catch (\InvalidArgumentException $exception) {
                throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->getMessage(), $exception);
            }
        }
        if ($promotionTouched) {
            if (null === $resolvedPromotionPriceTnd || null === $resolvedPromotionEndsOnDate) {
                $merchantProduct
                    ->setPromotionPriceTnd(null)
                    ->setPromotionEndsOn(null);
            } else {
                try {
                    $merchantProduct
                        ->setPromotionPriceTnd($resolvedPromotionPriceTnd)
                        ->setPromotionEndsOn($resolvedPromotionEndsOnDate);
                } catch (\InvalidArgumentException $exception) {
                    throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->getMessage(), $exception);
                }
            }
        }
        if (null !== $data->isAvailable) {
            $merchantProduct->setAvailable($data->isAvailable);
        }
        if (null !== $data->isVisible) {
            if ($data->isVisible && bccomp($merchantProduct->getPriceTnd(), '0.000', 3) <= 0) {
                throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'MERCHANT_PRODUCT_PRICE_REQUIRED_FOR_VISIBILITY');
            }
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
