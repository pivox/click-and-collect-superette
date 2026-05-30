<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\KadhiaOutput;
use App\Dto\KadhiaLineUpsertInput;
use App\Entity\KadhiaLine;
use App\Entity\User;
use App\Enum\KadhiaStatus;
use App\Factory\KadhiaOutputFactory;
use App\Repository\KadhiaLineRepository;
use App\Repository\KadhiaRepository;
use App\Repository\MerchantProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<KadhiaLineUpsertInput, KadhiaOutput>
 */
final readonly class UpsertKadhiaLineProcessor implements ProcessorInterface
{
    public function __construct(
        private MerchantProductRepository $merchantProductRepository,
        private KadhiaRepository $kadhiaRepository,
        private KadhiaLineRepository $kadhiaLineRepository,
        private EntityManagerInterface $entityManager,
        private KadhiaOutputFactory $kadhiaOutputFactory,
        private Security $security,
        #[Autowire(service: 'monolog.logger.order')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): KadhiaOutput
    {
        if (!$data instanceof KadhiaLineUpsertInput) {
            throw new \InvalidArgumentException('KadhiaLineUpsertInput expected.');
        }

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

        $merchantProductId = (string) ($uriVariables['merchantProductId'] ?? '');
        if (!Uuid::isValid($merchantProductId)) {
            throw new NotFoundHttpException('MERCHANT_PRODUCT_NOT_FOUND');
        }

        $merchantProduct = $this->merchantProductRepository->find($merchantProductId);
        if (null === $merchantProduct
            || !$merchantProduct->getShop()->getId()->equals($kadhia->getShop()->getId())
            || !$merchantProduct->isAvailable()
            || !$merchantProduct->isVisible()) {
            throw new NotFoundHttpException('MERCHANT_PRODUCT_NOT_FOUND');
        }

        $line = $this->kadhiaLineRepository->findOneByKadhiaAndProduct($kadhia, $merchantProduct);
        $isNew = null === $line;

        if ($isNew) {
            $line = (new KadhiaLine())
                ->setMerchantProduct($merchantProduct)
                ->setUnitPriceTnd($merchantProduct->getPriceTnd())
                ->setQuantity($data->quantity);
            $kadhia->addLine($line);
            $this->entityManager->persist($line);
        } else {
            $line->setQuantity($data->quantity);
        }

        $this->entityManager->flush();

        $this->logger->info('kadhia.line.upserted', [
            'kadhia_id' => $kadhiaId,
            'merchant_product_id' => $merchantProductId,
            'quantity' => $data->quantity,
            'action' => $isNew ? 'created' : 'updated',
        ]);

        return $this->kadhiaOutputFactory->toOutput($kadhia);
    }
}
