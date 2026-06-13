<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Kadhia;
use App\Entity\KadhiaMember;
use App\Entity\User;
use App\Repository\KadhiaMemberRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class KadhiaMembershipService
{
    public function __construct(
        private KadhiaMemberRepository $kadhiaMemberRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function isMember(Kadhia $kadhia, User $user): bool
    {
        return null !== $this->kadhiaMemberRepository->findOneByKadhiaAndUser($kadhia, $user);
    }

    public function ensureEditor(Kadhia $kadhia, User $user): bool
    {
        if (null !== $this->kadhiaMemberRepository->findOneByKadhiaAndUser($kadhia, $user)) {
            return false;
        }

        $member = (new KadhiaMember())
            ->setKadhia($kadhia)
            ->setUser($user)
            ->setRole('editor');

        $this->entityManager->persist($member);

        return true;
    }
}
