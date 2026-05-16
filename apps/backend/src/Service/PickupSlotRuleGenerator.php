<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PickupSlot;
use App\Entity\Shop;
use App\Repository\PickupSlotRepository;
use App\Repository\PickupSlotRuleRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PickupSlotRuleGenerator
{
    public const TIMEZONE = 'Africa/Tunis';

    public function __construct(
        private PickupSlotRuleRepository $pickupSlotRuleRepository,
        private PickupSlotRepository $pickupSlotRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function generateForShop(Shop $shop, ?\DateTimeImmutable $now = null): PickupSlotRuleGenerationResult
    {
        $timezone = new \DateTimeZone(self::TIMEZONE);
        $now = ($now ?? new \DateTimeImmutable('now', $timezone))->setTimezone($timezone);
        $horizonStart = $now->setTime(0, 0, 0);
        $horizonEnd = $horizonStart->modify('+4 weeks');
        $generatedCount = 0;
        $skippedExistingCount = 0;

        foreach ($this->pickupSlotRuleRepository->findActiveForShop($shop) as $rule) {
            for ($date = $horizonStart; $date < $horizonEnd; $date = $date->modify('+1 day')) {
                if ((int) $date->format('N') !== $rule->getWeekday()) {
                    continue;
                }

                $startsAt = $this->combineDateAndTime($date, $rule->getStartTime(), $timezone);
                $endsAt = $this->combineDateAndTime($date, $rule->getEndTime(), $timezone);

                if ($startsAt <= $now) {
                    continue;
                }

                if (
                    null !== $this->pickupSlotRepository->findOneForShopAndRange($shop, $startsAt, $endsAt)
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
                ++$generatedCount;
            }
        }

        $this->entityManager->flush();

        return new PickupSlotRuleGenerationResult(
            generatedCount: $generatedCount,
            skippedExistingCount: $skippedExistingCount,
            horizonStart: $horizonStart,
            horizonEnd: $horizonEnd,
        );
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
