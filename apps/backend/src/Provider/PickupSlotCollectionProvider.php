<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\PickupSlotCollectionOutput;
use App\ApiResource\PickupSlotOutput;
use App\Entity\PickupSlot;
use App\Repository\ExceptionalClosureRepository;
use App\Repository\PickupSlotRepository;
use App\Repository\ShopRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<PickupSlotCollectionOutput>
 */
final readonly class PickupSlotCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private PickupSlotRepository $pickupSlotRepository,
        private ExceptionalClosureRepository $exceptionalClosureRepository,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PickupSlotCollectionOutput
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop || !$shop->isActive()) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $dateParam = $this->requestStack->getCurrentRequest()?->query->getString('date', 'today');
        [$from, $to] = $this->resolveDayWindow($dateParam);

        $activeClosures = $this->exceptionalClosureRepository->findActiveForShop($shop);
        $availableSlots = array_values(array_filter(
            $this->pickupSlotRepository->findAvailableForShop($shop, $from),
            static fn (PickupSlot $slot): bool => !self::overlapsActiveClosure($activeClosures, $slot)
                && $slot->getStartsAt() < $to,
        ));

        $items = array_map(
            static fn (PickupSlot $slot): PickupSlotOutput => new PickupSlotOutput(
                id: $slot->getId()->toRfc4122(),
                startsAt: $slot->getStartsAt()->format(\DateTimeInterface::ATOM),
                endsAt: $slot->getEndsAt()->format(\DateTimeInterface::ATOM),
                capacity: $slot->getCapacity(),
                availableCount: $slot->getAvailableCount(),
            ),
            $availableSlots,
        );

        return new PickupSlotCollectionOutput($storeId, $items);
    }

    /**
     * @param list<\App\Entity\ExceptionalClosure> $activeClosures
     */
    private static function overlapsActiveClosure(array $activeClosures, PickupSlot $slot): bool
    {
        foreach ($activeClosures as $closure) {
            if ($closure->getStartsAt() < $slot->getEndsAt() && $closure->getEndsAt() > $slot->getStartsAt()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns [from, to) boundaries for the requested day in UTC.
     * "today"    → [now, start of tomorrow)
     * "tomorrow" → [start of tomorrow, start of day+2)
     * "after"    → [start of day+2, start of day+3).
     *
     * @return array{\DateTimeImmutable, \DateTimeImmutable}
     */
    private function resolveDayWindow(string $dateParam): array
    {
        $utc = new \DateTimeZone('UTC');
        $tomorrow = new \DateTimeImmutable('tomorrow midnight', $utc);

        return match ($dateParam) {
            'tomorrow' => [$tomorrow, $tomorrow->modify('+1 day')],
            'after' => [$tomorrow->modify('+1 day'), $tomorrow->modify('+2 days')],
            default => [new \DateTimeImmutable('now', $utc), $tomorrow],
        };
    }
}
