<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PlatformSetting;
use App\Repository\PlatformSettingRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PlatformSettingsManager
{
    public function __construct(
        private PlatformSettingRepository $platformSettingRepository,
        private EntityManagerInterface $entityManager,
        private FrontendOriginValidator $frontendOriginValidator,
        private string $frontendUrl,
    ) {
    }

    public function current(): PlatformSetting
    {
        $setting = $this->platformSettingRepository->findCurrent();
        if ($setting instanceof PlatformSetting) {
            return $setting;
        }

        $setting = new PlatformSetting($this->frontendOriginValidator->normalize($this->frontendUrl));
        $this->entityManager->persist($setting);
        $this->entityManager->flush();

        return $setting;
    }
}
