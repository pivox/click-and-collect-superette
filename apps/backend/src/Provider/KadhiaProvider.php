<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\KadhiaOutput;
use App\Entity\User;
use App\Factory\KadhiaOutputFactory;
use App\Repository\KadhiaRepository;
use App\Repository\OrderRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<KadhiaOutput>
 */
final readonly class KadhiaProvider implements ProviderInterface
{
    public function __construct(
        private KadhiaRepository $kadhiaRepository,
        private KadhiaOutputFactory $kadhiaOutputFactory,
        private OrderRepository $orderRepository,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): KadhiaOutput
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

        $order = $this->orderRepository->findOneBy(['kadhia' => $kadhia]);

        return $this->kadhiaOutputFactory->toOutput($kadhia, $order?->getId()->toRfc4122());
    }
}
