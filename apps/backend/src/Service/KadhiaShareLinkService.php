<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Kadhia;
use App\Entity\KadhiaShareLink;
use App\Entity\User;
use App\Repository\KadhiaShareLinkRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class KadhiaShareLinkService
{
    private const int TTL_DAYS = 7;

    public function __construct(
        private KadhiaShareLinkRepository $kadhiaShareLinkRepository,
        private EntityManagerInterface $entityManager,
        private ManagerRegistry $managerRegistry,
        private ClockInterface $clock,
        #[Autowire('%kernel.secret%')]
        private string $secret,
        private string $frontendUrl,
    ) {
    }

    /**
     * @return array{0: KadhiaShareLink, 1: string}
     */
    public function getOrCreateActiveLink(Kadhia $kadhia, User $creator): array
    {
        try {
            /** @var array{0: KadhiaShareLink, 1: string} */
            return $this->entityManager->wrapInTransaction(function () use ($kadhia, $creator): array {
                $now = \DateTimeImmutable::createFromInterface($this->clock->now());
                $activeRows = $this->kadhiaShareLinkRepository->findActiveRowsForKadhia($kadhia);
                $deactivatedExpiredLink = false;

                foreach ($activeRows as $activeRow) {
                    if ($activeRow->isActiveAt($now)) {
                        return [$activeRow, $this->tokenFor($activeRow)];
                    }

                    $activeRow->setActive(false);
                    $deactivatedExpiredLink = true;
                }

                if ($deactivatedExpiredLink) {
                    $this->entityManager->flush();
                }

                $link = (new KadhiaShareLink())
                    ->setKadhia($kadhia)
                    ->setCreatedBy($creator)
                    ->setExpiresAt($now->modify('+'.self::TTL_DAYS.' days'));

                $token = $this->tokenFor($link);
                $link->setTokenHash($this->hashToken($token));

                $this->entityManager->persist($link);
                $this->entityManager->flush();

                return [$link, $token];
            });
        } catch (UniqueConstraintViolationException $exception) {
            $now = \DateTimeImmutable::createFromInterface($this->clock->now());
            $repository = $this->managerRegistry->resetManager()->getRepository(KadhiaShareLink::class);
            if (!$repository instanceof KadhiaShareLinkRepository) {
                throw $exception;
            }

            $existing = $repository->findActiveForKadhia($kadhia, $now);
            if (null !== $existing) {
                return [$existing, $this->tokenFor($existing)];
            }

            throw $exception;
        }
    }

    public function findActiveByToken(string $token): ?KadhiaShareLink
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $link = $this->kadhiaShareLinkRepository->findActiveByTokenHash($this->hashToken($token), $now);
        if (null !== $link) {
            return $link;
        }

        return null;
    }

    public function shareUrl(string $token): string
    {
        return rtrim($this->frontendUrl, '/').'/kadhia/share/'.rawurlencode($token);
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function tokenFor(KadhiaShareLink $link): string
    {
        $payload = implode('|', [
            $link->getId()->toRfc4122(),
            $link->getKadhia()->getId()->toRfc4122(),
            $link->getCreatedBy()->getId()->toRfc4122(),
            $link->getCreatedAt()->format(\DateTimeInterface::ATOM),
            $link->getExpiresAt()->format(\DateTimeInterface::ATOM),
        ]);

        return rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $this->secret, true)), '+/', '-_'), '=');
    }
}
