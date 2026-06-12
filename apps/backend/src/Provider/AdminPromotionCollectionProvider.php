<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminPromotionItemOutput;
use App\ApiResource\AdminPromotionListOutput;
use App\Entity\MerchantProduct;
use App\Repository\AdminPromotionRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<AdminPromotionListOutput>
 */
final readonly class AdminPromotionCollectionProvider implements ProviderInterface
{
    private const DEFAULT_PAGE = 1;
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 50;

    public function __construct(
        private AdminPromotionRepository $adminPromotionRepository,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminPromotionListOutput
    {
        $request = $this->requestStack->getCurrentRequest();
        $page = $this->parsePositiveInt($request?->query->get('page'), self::DEFAULT_PAGE, 'ADMIN_PROMOTION_INVALID_PAGE');
        $limit = $this->parsePositiveInt($request?->query->get('limit'), self::DEFAULT_LIMIT, 'ADMIN_PROMOTION_INVALID_LIMIT');
        $limit = min(self::MAX_LIMIT, $limit);
        $status = $this->parseOptionalStatus($request?->query->get('promotion_status'));
        $storeId = $this->parseOptionalUuid($request?->query->get('store_id'), 'ADMIN_PROMOTION_INVALID_STORE_FILTER');
        $merchantId = $this->parseOptionalUuid($request?->query->get('merchant_id'), 'ADMIN_PROMOTION_INVALID_MERCHANT_FILTER');
        $offset = ($page - 1) * $limit;

        $products = $this->adminPromotionRepository->findPaginated($limit, $offset, $status, $storeId, $merchantId);

        return new AdminPromotionListOutput(
            id: 'admin-promotions',
            items: array_map($this->mapItem(...), $products),
            page: $page,
            limit: $limit,
            total: $this->adminPromotionRepository->countFiltered($status, $storeId, $merchantId),
        );
    }

    private function mapItem(MerchantProduct $product): AdminPromotionItemOutput
    {
        $owner = $product->getShop()->getOwner();

        return new AdminPromotionItemOutput(
            id: $product->getId()->toRfc4122(),
            productName: $product->getDisplayNameFr(),
            storeId: $product->getShop()->getId()->toRfc4122(),
            storeName: $product->getShop()->getName(),
            merchantId: $owner?->getId()->toRfc4122(),
            merchantEmail: $owner?->getEmail(),
            // Repository filters guarantee both promotion fields are non-null.
            priceTnd: $product->getPriceTnd(),
            promotionPriceTnd: $product->getPromotionPriceTnd() ?? '',
            promotionEndsOn: $product->getPromotionEndsOn()?->format('Y-m-d') ?? '',
            promotionActive: $product->isPromotionActive(),
            isAvailable: $product->isAvailable(),
            isVisible: $product->isVisible(),
        );
    }

    private function parsePositiveInt(mixed $raw, int $default, string $errorCode): int
    {
        if (null === $raw || '' === $raw) {
            return $default;
        }

        if (false === filter_var($raw, \FILTER_VALIDATE_INT)) {
            throw new BadRequestHttpException($errorCode);
        }

        $value = (int) $raw;
        if ($value < 1) {
            throw new BadRequestHttpException($errorCode);
        }

        return $value;
    }

    private function parseOptionalStatus(mixed $raw): ?string
    {
        if (null === $raw || '' === $raw) {
            return null;
        }

        if (!\in_array($raw, [AdminPromotionRepository::STATUS_ACTIVE, AdminPromotionRepository::STATUS_EXPIRED], true)) {
            throw new BadRequestHttpException('ADMIN_PROMOTION_INVALID_STATUS_FILTER');
        }

        return $raw;
    }

    private function parseOptionalUuid(mixed $raw, string $errorCode): ?string
    {
        if (null === $raw || '' === $raw) {
            return null;
        }

        if (!\is_string($raw) || !Uuid::isValid($raw)) {
            throw new BadRequestHttpException($errorCode);
        }

        return $raw;
    }
}
