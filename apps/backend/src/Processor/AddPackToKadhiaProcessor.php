<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\KadhiaOutput;
use App\Entity\KadhiaLine;
use App\Entity\User;
use App\Enum\KadhiaStatus;
use App\Factory\KadhiaOutputFactory;
use App\Repository\KadhiaLineRepository;
use App\Repository\KadhiaRepository;
use App\Repository\ProductPackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<null, KadhiaOutput>
 */
final readonly class AddPackToKadhiaProcessor implements ProcessorInterface
{
    public function __construct(
        private KadhiaRepository $kadhiaRepository,
        private KadhiaLineRepository $kadhiaLineRepository,
        private ProductPackRepository $productPackRepository,
        private EntityManagerInterface $entityManager,
        private KadhiaOutputFactory $kadhiaOutputFactory,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): KadhiaOutput
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
            throw new UnprocessableEntityHttpException('KADHIA_NOT_EDITABLE');
        }

        $packId = (string) ($uriVariables['packId'] ?? '');
        if (!Uuid::isValid($packId)) {
            throw new NotFoundHttpException('PRODUCT_PACK_NOT_FOUND');
        }

        $pack = $this->productPackRepository->find($packId);
        if (null === $pack || !$pack->getShop()->getId()->equals($kadhia->getShop()->getId()) || !$pack->isActive()) {
            throw new NotFoundHttpException('PRODUCT_PACK_NOT_FOUND');
        }

        // Reload pack to ensure items are properly loaded
        $this->entityManager->refresh($pack);

        // Validate all items are available BEFORE adding any
        foreach ($pack->getItems() as $packItem) {
            $merchantProduct = $packItem->getMerchantProduct();
            if (!$merchantProduct->isAvailable() || !$merchantProduct->isVisible()) {
                throw new UnprocessableEntityHttpException('PACK_CONTAINS_UNAVAILABLE_ITEMS');
            }
        }

        // All items are available, proceed to add them
        foreach ($pack->getItems() as $packItem) {
            $merchantProduct = $packItem->getMerchantProduct();

            $existingLine = $this->kadhiaLineRepository->findOneByKadhiaAndProduct($kadhia, $merchantProduct);

            if (null === $existingLine) {
                $line = (new KadhiaLine())
                    ->setMerchantProduct($merchantProduct)
                    ->setUnitPriceTnd($merchantProduct->getPriceTnd())
                    ->setQuantity($packItem->getQuantity());
                $kadhia->addLine($line);
                $this->entityManager->persist($line);
            } else {
                $newQty = $existingLine->getQuantity() + $packItem->getQuantity();
                $existingLine->setQuantity($newQty);
            }
        }

        $this->entityManager->flush();

        return $this->kadhiaOutputFactory->toOutput($kadhia);
    }
}
