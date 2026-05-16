<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Shop;
use App\Repository\PickupSlotRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class ExceptionalClosureImpactService
{
    public function __construct(
        private PickupSlotRepository $pickupSlotRepository,
    ) {
    }

    public function applyClosureImpact(Shop $shop, \DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt): void
    {
        if ($this->pickupSlotRepository->hasActiveBookedOverlapForShop($shop, $startsAt, $endsAt)) {
            throw new HttpException(Response::HTTP_CONFLICT, 'EXCEPTIONAL_CLOSURE_HAS_BOOKED_SLOTS');
        }

        foreach ($this->pickupSlotRepository->findActiveUnbookedOverlappingForShop($shop, $startsAt, $endsAt) as $slot) {
            $slot->setActive(false);
        }
    }
}
