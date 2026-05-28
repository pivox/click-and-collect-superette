<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Enum\KadhiaStatus;
use App\Repository\KadhiaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<object, void>
 */
final readonly class DeleteKadhiaProcessor implements ProcessorInterface
{
    public function __construct(
        private KadhiaRepository $kadhiaRepository,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('CUSTOMER_ACCESS_REQUIRED');
        }

        $kadhiaId = (string) ($uriVariables['kadhiaId'] ?? '');
        if (!Uuid::isValid($kadhiaId)) {
            throw new NotFoundHttpException('KADHIA_NOT_FOUND');
        }

        $kadhia = $this->kadhiaRepository->findByIdAndCustomer($kadhiaId, $user);
        if (null === $kadhia) {
            throw new NotFoundHttpException('KADHIA_NOT_FOUND');
        }

        if (KadhiaStatus::Draft !== $kadhia->getStatus()) {
            throw new UnprocessableEntityHttpException('KADHIA_NOT_DELETABLE');
        }

        $this->entityManager->remove($kadhia);
        $this->entityManager->flush();
    }
}
