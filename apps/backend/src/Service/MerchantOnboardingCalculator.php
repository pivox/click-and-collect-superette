<?php

declare(strict_types=1);

namespace App\Service;

use App\ApiResource\MerchantOnboardingOutput;
use App\ApiResource\MerchantOnboardingStepOutput;
use App\Entity\Shop;
use App\Entity\User;
use App\Repository\MerchantProductRepository;
use App\Repository\PickupSlotRepository;
use App\Repository\PickupSlotRuleRepository;
use App\Repository\ShopRepository;
use App\Repository\ShopThemeRepository;

final readonly class MerchantOnboardingCalculator
{
    public function __construct(
        private ShopRepository $shopRepository,
        private ShopThemeRepository $shopThemeRepository,
        private MerchantProductRepository $merchantProductRepository,
        private PickupSlotRuleRepository $pickupSlotRuleRepository,
        private PickupSlotRepository $pickupSlotRepository,
    ) {
    }

    public function calculate(User $merchant): MerchantOnboardingOutput
    {
        $shops = $this->shopRepository->findBy(['owner' => $merchant, 'active' => true]);

        $hasShop = [] !== $shops;
        $storeProfile = $hasShop;
        $theme = $hasShop && $this->anyShopHasTheme($shops);
        $catalog = $hasShop && $this->anyShopHasVisibleProduct($shops);
        $pickupSlots = $hasShop && $this->anyShopHasPickupSlotConfigured($shops);
        $qrCode = $hasShop;

        $steps = [
            new MerchantOnboardingStepOutput(
                key: 'store_profile',
                label: 'Compléter la supérette',
                completed: $storeProfile,
            ),
            new MerchantOnboardingStepOutput(
                key: 'theme',
                label: 'Personnaliser le thème',
                completed: $theme,
            ),
            new MerchantOnboardingStepOutput(
                key: 'catalog',
                label: 'Ajouter des produits',
                completed: $catalog,
            ),
            new MerchantOnboardingStepOutput(
                key: 'pickup_slots',
                label: 'Configurer les créneaux',
                completed: $pickupSlots,
            ),
            new MerchantOnboardingStepOutput(
                key: 'qr_code',
                label: 'Accéder au QR code',
                completed: $qrCode,
            ),
        ];

        $completedAt = $merchant->getOnboardingCompletedAt();

        return new MerchantOnboardingOutput(
            id: $merchant->getId()->toRfc4122(),
            completed: null !== $completedAt,
            completedAt: $completedAt?->format(\DateTimeInterface::ATOM),
            steps: $steps,
        );
    }

    /** @param list<Shop> $shops */
    private function anyShopHasTheme(array $shops): bool
    {
        foreach ($shops as $shop) {
            if ($this->shopThemeRepository->existsForShop($shop)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<Shop> $shops */
    private function anyShopHasVisibleProduct(array $shops): bool
    {
        foreach ($shops as $shop) {
            if ($this->merchantProductRepository->count(['shop' => $shop, 'isVisible' => true]) > 0) {
                return true;
            }
        }

        return false;
    }

    /** @param list<Shop> $shops */
    private function anyShopHasPickupSlotConfigured(array $shops): bool
    {
        foreach ($shops as $shop) {
            foreach ($this->pickupSlotRuleRepository->findActiveForShop($shop) as $rule) {
                if (PickupSlotDuration::isExactlyOneHour($rule->getStartTime(), $rule->getEndTime())) {
                    return true;
                }
            }

            $now = PickupSlotDisplayTime::fromStoredLocalClock(new \DateTimeImmutable());
            foreach ($this->pickupSlotRepository->findForShop($shop) as $slot) {
                $startsAt = PickupSlotDisplayTime::fromStoredLocalClock($slot->getStartsAt());
                $endsAt = PickupSlotDisplayTime::fromStoredLocalClock($slot->getEndsAt());

                if ($slot->isActive() && $startsAt > $now && PickupSlotDuration::isExactlyOneHour($startsAt, $endsAt)) {
                    return true;
                }
            }
        }

        return false;
    }
}
