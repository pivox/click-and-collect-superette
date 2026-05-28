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
use App\Service\PickupSlotRuleGenerator;
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

        $activeClosures = $this->exceptionalClosureRepository->findActiveForShop($shop);
        $availableSlots = array_values(array_filter(
            $this->pickupSlotRepository->findAvailableForShop($shop, $from),
            static fn (PickupSlot $slot): bool => !self::overlapsActiveClosure($activeClosures, $slot)
                && (null === $to || $slot->getStartsAt() < $to),
        ));

        $tunis = new \DateTimeZone(PickupSlotRuleGenerator::TIMEZONE);
        $items = array_map(
            static fn (PickupSlot $slot): PickupSlotOutput => new PickupSlotOutput(
                id: $slot->getId()->toRfc4122(),
                startsAt: self::reinterpretAsLocalTime($slot->getStartsAt(), $tunis),
                endsAt: self::reinterpretAsLocalTime($slot->getEndsAt(), $tunis),
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
     * The slot generator stores Africa/Tunis local times in a TIMESTAMP WITHOUT TIME ZONE
     * column. Doctrine reads them back in the default PHP timezone (UTC), producing a
     * datetime with the wrong offset. We reinterpret the stored time components as
     * Africa/Tunis so the client receives the correct ISO-8601 offset (+01:00).
     */
    private static function reinterpretAsLocalTime(\DateTimeImmutable $dt, \DateTimeZone $tz): string
    {
        return (\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dt->format('Y-m-d H:i:s'), $tz) ?: $dt)
            ->format(\DateTimeInterface::ATOM);
    }

    /**
     * Returns [from, to) boundaries for the requested day in UTC.
     * null       → [now, null) — all future slots, no upper bound (backward-compatible)
     * "today"    → [now, start of tomorrow)
     * "tomorrow" → [start of tomorrow, start of day+2)
     * "after"    → [start of day+2, start of day+3).
     *
     * @return array{\DateTimeImmutable, \DateTimeImmutable|null}
     */
    private function resolveDayWindow(?string $dateParam): array
    {
        $utc = new \DateTimeZone('UTC');
        $tomorrow = new \DateTimeImmutable('tomorrow midnight', $utc);

        return match ($dateParam) {
            'today' => [new \DateTimeImmutable('now', $utc), $tomorrow],
            'tomorrow' => [$tomorrow, $tomorrow->modify('+1 day')],
            'after' => [$tomorrow->modify('+1 day'), $tomorrow->modify('+2 days')],
            default => [new \DateTimeImmutable('now', $utc), null],
        };
    }
}
