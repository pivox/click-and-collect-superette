<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantProductPriceUpdateOutput;
use App\Dto\MerchantProductPriceUpdateInput;
use App\Entity\User;
use App\Enum\MerchantProductPriceChangeType;
use App\Enum\MerchantProductPriceSource;
use App\Mapper\MerchantProductPriceHistoryMapper;
use App\Repository\MerchantProductRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\MerchantProductPriceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<MerchantProductPriceUpdateInput, MerchantProductPriceUpdateOutput>
 */
final readonly class UpdateMerchantProductPriceProcessor implements ProcessorInterface
{
    public function __construct(
        private MerchantProductRepository $merchantProductRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private MerchantProductPriceService $priceService,
        private MerchantProductPriceHistoryMapper $mapper,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantProductPriceUpdateOutput
    {
        if (!$data instanceof MerchantProductPriceUpdateInput) {
            throw new \InvalidArgumentException('MerchantProductPriceUpdateInput expected.');
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

        $user = $this->security->getUser();
        $lastPriceChange = $this->priceService->changePrice(
            merchantProduct: $merchantProduct,
            newPrice: $data->price,
            changeType: MerchantProductPriceChangeType::ManualUpdate,
            source: MerchantProductPriceSource::MerchantDashboard,
            changedByUser: $user instanceof User ? $user : null,
            reason: $data->reason,
            currency: $data->currency,
        );

        $this->entityManager->flush();

        return $this->mapper->toUpdateOutput($merchantProduct, $data->currency, $lastPriceChange);
    }
}
