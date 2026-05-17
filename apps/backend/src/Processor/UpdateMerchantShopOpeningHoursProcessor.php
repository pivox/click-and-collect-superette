<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\ShopOpeningHoursOutput;
use App\Dto\ShopOpeningHoursPatchInput;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\OpeningHoursValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<ShopOpeningHoursPatchInput, ShopOpeningHoursOutput>
 */
final readonly class UpdateMerchantShopOpeningHoursProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private OpeningHoursValidator $openingHoursValidator,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ShopOpeningHoursOutput
    {
        if (!$data instanceof ShopOpeningHoursPatchInput) {
            throw new \InvalidArgumentException('ShopOpeningHoursPatchInput expected.');
        }

        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        $openingHours = $this->openingHoursValidator->validateAndNormalize($data->openingHours);
        $shop->setOpeningHours($openingHours);
        $this->entityManager->flush();

        return new ShopOpeningHoursOutput(
            storeId: $shop->getId()->toRfc4122(),
            openingHours: $shop->getOpeningHours(),
        );
    }
}
