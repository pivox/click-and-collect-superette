<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\KadhiaOutput;
use App\Entity\Kadhia;
use App\Entity\User;
use App\Factory\KadhiaOutputFactory;
use App\Repository\KadhiaRepository;
use App\Repository\ShopRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        private ShopRepository $shopRepository,
        private KadhiaRepository $kadhiaRepository,
        private EntityManagerInterface $entityManager,
        private KadhiaOutputFactory $kadhiaOutputFactory,
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

        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop || !$shop->isActive()) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $kadhia = $this->kadhiaRepository->findDraftByCustomerAndShop($user, $shop);
        if (null === $kadhia) {
            $kadhia = (new Kadhia())->setCustomer($user)->setShop($shop);
            $this->entityManager->persist($kadhia);
            $this->entityManager->flush();
        }

        return $this->kadhiaOutputFactory->toOutput($kadhia);
    }
}
