<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\StoreSuggestionsOutput;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Mapper\StoreCatalogProductBatchMapper;
use App\Repository\KadhiaRepository;
use App\Repository\OrderRepository;
use App\Repository\ShopRepository;
use App\Service\KadhiaSuggestionService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<StoreSuggestionsOutput>
 */
final readonly class StoreSuggestionsProvider implements ProviderInterface
{
    private const int DEFAULT_LIMIT = 10;
    private const int MAX_LIMIT = 20;

    /** Cap applied in PHP — see OrderRepository::findRecentByCustomerAndShopWithLines. */
    private const int MAX_HISTORY_ORDERS = 30;

    private const array HISTORY_STATUSES = [
        OrderStatus::Submitted,
        OrderStatus::Accepted,
        OrderStatus::PartiallyAccepted,
        OrderStatus::Preparing,
        OrderStatus::Ready,
        OrderStatus::PickupPending,
        OrderStatus::Completed,
    ];

    public function __construct(
        private ShopRepository $shopRepository,
        private OrderRepository $orderRepository,
        private KadhiaRepository $kadhiaRepository,
        private KadhiaSuggestionService $kadhiaSuggestionService,
        private StoreCatalogProductBatchMapper $storeCatalogProductBatchMapper,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): StoreSuggestionsOutput
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('CUSTOMER_ACCESS_REQUIRED');
        }

        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop || !$shop->isActive()) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $request = $this->requestStack->getCurrentRequest();

        $limit = self::DEFAULT_LIMIT;
        $rawLimit = $request?->query->get('limit');
        if (null !== $rawLimit && '' !== $rawLimit) {
            if (1 !== preg_match('/^\d+$/', (string) $rawLimit) || (int) $rawLimit < 1 || (int) $rawLimit > self::MAX_LIMIT) {
                throw new BadRequestHttpException('INVALID_LIMIT');
            }
            $limit = (int) $rawLimit;
        }

        $seedProductIds = $this->resolveSeedProductIds($request?->query->getString('kadhia_id') ?: null, $user, $shop);

        $orders = $this->orderRepository->findRecentByCustomerAndShopWithLines($user, $shop, self::HISTORY_STATUSES);
        $orders = \array_slice($orders, 0, self::MAX_HISTORY_ORDERS);

        $frequentlyBoughtTogether = $this->kadhiaSuggestionService->buildFrequentlyBoughtTogether($orders, $seedProductIds, $limit);
        $recentlyOrdered = $this->kadhiaSuggestionService->buildRecentlyOrdered($orders, $seedProductIds, $limit);

        return new StoreSuggestionsOutput(
            storeId: $storeId,
            frequentlyBoughtTogether: $this->storeCatalogProductBatchMapper->toOutputs($frequentlyBoughtTogether),
            recentlyOrdered: $this->storeCatalogProductBatchMapper->toOutputs($recentlyOrdered),
        );
    }

    /**
     * A kadhia_id that is unknown, owned by another customer or attached to
     * another shop yields an empty seed (no error, no information leak).
     *
     * @return list<string>
     */
    private function resolveSeedProductIds(?string $kadhiaId, User $user, Shop $shop): array
    {
        if (null === $kadhiaId) {
            return [];
        }

        if (!Uuid::isValid($kadhiaId)) {
            throw new BadRequestHttpException('INVALID_KADHIA_ID');
        }

        $kadhia = $this->kadhiaRepository->findByIdAndCustomer($kadhiaId, $user);
        if (null === $kadhia || $kadhia->getShop()->getId()->toRfc4122() !== $shop->getId()->toRfc4122()) {
            return [];
        }

        $seedProductIds = [];
        foreach ($kadhia->getLines() as $line) {
            $seedProductIds[] = $line->getMerchantProduct()->getId()->toRfc4122();
        }

        return $seedProductIds;
    }
}
