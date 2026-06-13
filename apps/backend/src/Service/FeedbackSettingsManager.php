<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\FeedbackSetting;
use App\Entity\User;
use App\Enum\FeedbackAppArea;
use App\Repository\FeedbackSettingRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class FeedbackSettingsManager
{
    public function __construct(
        private FeedbackSettingRepository $feedbackSettingRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function current(): FeedbackSetting
    {
        $setting = $this->feedbackSettingRepository->findOneBy([]);
        if (!$setting instanceof FeedbackSetting) {
            $setting = new FeedbackSetting();
            $this->entityManager->persist($setting);
        }

        return $setting;
    }

    public function isEnabledFor(User $user, FeedbackAppArea $appArea): bool
    {
        return $this->current()->isEnabledFor($this->userRole($user), $appArea);
    }

    public function userRole(User $user): string
    {
        $roles = $user->getRoles();
        if (\in_array('ROLE_ADMIN', $roles, true)) {
            return 'admin';
        }
        if (\in_array('ROLE_MERCHANT', $roles, true)) {
            return 'merchant';
        }

        return 'client';
    }
}
