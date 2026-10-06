<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PickupSlot;
use App\Entity\Shop;
use App\Repository\ExceptionalClosureRepository;
use App\Repository\PickupSlotRepository;
use App\Repository\PickupSlotRuleRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PickupSlotRuleGenerator
{
    public const TIMEZONE = 'Africa/Tunis';

    public function __construct(
        private PickupSlotRuleRepository $pickupSlotRuleRepository,
        private PickupSlotRepository $pickupSlotRepository,
        private ExceptionalClosureRepository $exceptionalClosureRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function generateForShop(Shop $shop, ?\DateTimeImmutable $now = null, int $horizonMonths = 1): PickupSlotRuleGenerationResult
    {
        return $this->entityManager->wrapInTransaction(function () use ($shop, $now, $horizonMonths): PickupSlotRuleGenerationResult {
            $this->entityManager->lock($shop, LockMode::PESSIMISTIC_WRITE);

            return $this->generateWhileShopLocked($shop, $now, $horizonMonths);
        });
    }

    private function generateWhileShopLocked(Shop $shop, ?\DateTimeImmutable $now, int $horizonMonths): PickupSlotRuleGenerationResult
    {
        $timezone = new \DateTimeZone(self::TIMEZONE);
        $now = ($now ?? new \DateTimeImmutable('now', $timezone))->setTimezone($timezone);
        $horizonStart = $now->setTime(0, 0, 0);
        $targetMonth = $horizonStart->modify('first day of this month')->modify("+{$horizonMonths} months");
        $horizonEnd = $targetMonth->setDate(
            (int) $targetMonth->format('Y'),
            (int) $targetMonth->format('m'),
            min((int) $horizonStart->format('d'), (int) $targetMonth->format('t')),
        );
        $generatedSlots = [];
        $generatedCount = 0;
        $skippedExistingCount = 0;
        $skippedClosureCount = 0;
        $activeClosures = $this->exceptionalClosureRepository->findActiveForShop($shop);

        foreach ($this->pickupSlotRuleRepository->findActiveForShop($shop) as $rule) {
            if (!PickupSlotDuration::isAtLeastOneHour($rule->getStartTime(), $rule->getEndTime())) {
                continue;
            }

            for ($date = $horizonStart; $date < $horizonEnd; $date = $date->modify('+1 day')) {
                if ((int) $date->format('N') !== $rule->getWeekday()) {
                    continue;
                }

                $rangeEndsAt = $this->combineDateAndTime($date, $rule->getEndTime(), $timezone);
                for (
                    $startsAt = $this->combineDateAndTime($date, $rule->getStartTime(), $timezone);
                    $startsAt->modify('+1 hour') <= $rangeEndsAt;
                    $startsAt = $startsAt->modify('+1 hour')
                ) {
                    $endsAt = $startsAt->modify('+1 hour');

                    if ($startsAt <= $now) {
                        continue;
                    }

                    if ($this->overlapsActiveClosure($activeClosures, $startsAt, $endsAt)) {
                        ++$skippedClosureCount;
                        continue;
                    }

                    if (
                        $this->overlapsGeneratedSlot($generatedSlots, $startsAt, $endsAt)
                        || null !== $this->pickupSlotRepository->findOneForShopAndRange($shop, $startsAt, $endsAt)
                        || $this->pickupSlotRepository->hasActiveOverlapForShop($shop, $startsAt, $endsAt)
                    ) {
                        ++$skippedExistingCount;
                        continue;
                    }

                    $slot = (new PickupSlot())
                        ->setShop($shop)
                        ->setStartsAt($startsAt)
                        ->setEndsAt($endsAt)
                        ->setCapacity($rule->getCapacity())
                        ->setActive(true);

                    $this->entityManager->persist($slot);
                    $generatedSlots[] = $slot;
                    ++$generatedCount;
                }
            }
        }

        return new PickupSlotRuleGenerationResult(
            generatedCount: $generatedCount,
            skippedExistingCount: $skippedExistingCount,
            skippedClosureCount: $skippedClosureCount,
            horizonStart: $horizonStart,
            horizonEnd: $horizonEnd,
        );
    }

    /**
     * @param list<PickupSlot> $generatedSlots
     */
    private function overlapsGeneratedSlot(array $generatedSlots, \DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt): bool
    {
        foreach ($generatedSlots as $slot) {
            if ($slot->getStartsAt() < $endsAt && $slot->getEndsAt() > $startsAt) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<\App\Entity\ExceptionalClosure> $activeClosures
     */
    private function overlapsActiveClosure(array $activeClosures, \DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt): bool
    {
        $slotStartsAt = PickupSlotDisplayTime::fromStoredLocalClock($startsAt);
        $slotEndsAt = PickupSlotDisplayTime::fromStoredLocalClock($endsAt);

        foreach ($activeClosures as $closure) {
            $closureStartsAt = PickupSlotDisplayTime::fromStoredLocalClock($closure->getStartsAt());
            $closureEndsAt = PickupSlotDisplayTime::fromStoredLocalClock($closure->getEndsAt());

            if ($closureStartsAt < $slotEndsAt && $closureEndsAt > $slotStartsAt) {
                return true;
            }
        }

        return false;
    }

    private function combineDateAndTime(
        \DateTimeImmutable $date,
        \DateTimeImmutable $time,
        \DateTimeZone $timezone,
    ): \DateTimeImmutable {
        return new \DateTimeImmutable(
            \sprintf('%s %s', $date->format('Y-m-d'), $time->format('H:i:s')),
            $timezone,
        );
    }
}
