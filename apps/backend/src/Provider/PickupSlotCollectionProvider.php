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
use App\Service\PickupSlotDisplayTime;
use App\Service\PickupSlotDuration;
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

        $dateParam = $this->requestStack->getCurrentRequest()?->query->get('date');
        [$from, $to] = $this->resolveDayWindow($dateParam);
        $to = null !== $to ? PickupSlotDisplayTime::fromStoredLocalClock($to) : null;

        $activeClosures = $this->exceptionalClosureRepository->findActiveForShop($shop);
        $availableSlots = array_values(array_filter(
            $this->pickupSlotRepository->findAvailableForShop($shop, $from),
            static fn (PickupSlot $slot): bool => !self::overlapsActiveClosure($activeClosures, $slot)
                && self::isOneHourSlot($slot)
                && (null === $to || PickupSlotDisplayTime::fromStoredLocalClock($slot->getStartsAt()) < $to),
        ));

        $items = array_map(
            static fn (PickupSlot $slot): PickupSlotOutput => new PickupSlotOutput(
                id: $slot->getId()->toRfc4122(),
                startsAt: PickupSlotDisplayTime::toLocalAtom($slot->getStartsAt()),
                endsAt: PickupSlotDisplayTime::toLocalAtom($slot->getEndsAt()),
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
        $slotStartsAt = PickupSlotDisplayTime::fromStoredLocalClock($slot->getStartsAt());
        $slotEndsAt = PickupSlotDisplayTime::fromStoredLocalClock($slot->getEndsAt());

        foreach ($activeClosures as $closure) {
            $closureStartsAt = PickupSlotDisplayTime::fromStoredLocalClock($closure->getStartsAt());
            $closureEndsAt = PickupSlotDisplayTime::fromStoredLocalClock($closure->getEndsAt());

            if ($closureStartsAt < $slotEndsAt && $closureEndsAt > $slotStartsAt) {
                return true;
            }
        }

        return false;
    }

    private static function isOneHourSlot(PickupSlot $slot): bool
    {
        return PickupSlotDuration::isExactlyOneHour(
            PickupSlotDisplayTime::fromStoredLocalClock($slot->getStartsAt()),
            PickupSlotDisplayTime::fromStoredLocalClock($slot->getEndsAt()),
        );
    }

    /**
     * Returns [from, to) boundaries for the requested day in Tunisia local time.
     * null       → [now, null) — all future slots, no upper bound (backward-compatible)
     * "today"    → [now, start of tomorrow)
     * "tomorrow" → [start of tomorrow, start of day+2)
     * "after"    → [start of day+2, start of day+3).
     *
     * @return array{\DateTimeImmutable, \DateTimeImmutable|null}
     */
    private function resolveDayWindow(?string $dateParam): array
    {
        $timezone = new \DateTimeZone('Africa/Tunis');
        $tomorrow = new \DateTimeImmutable('tomorrow midnight', $timezone);

        return match ($dateParam) {
            'today' => [new \DateTimeImmutable('now', $timezone), $tomorrow],
            'tomorrow' => [$tomorrow, $tomorrow->modify('+1 day')],
            'after' => [$tomorrow->modify('+1 day'), $tomorrow->modify('+2 days')],
            default => [new \DateTimeImmutable('now', $timezone), null],
        };
    }
}
