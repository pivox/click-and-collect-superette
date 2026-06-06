<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\KadhiaLine;
use App\Entity\MerchantProduct;
use App\Entity\OrderLine;
use App\Entity\ProductReference;
use App\Entity\ProductReferenceMergeHistory;
use App\Entity\User;
use App\Enum\ProductReferenceStatus;
use App\Repository\ProductReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class ProductReferenceDeduplicationService
{
    public function __construct(
        private ProductReferenceRepository $productReferenceRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<array{left: ProductReference, right: ProductReference, reason: string, barcode: ?string, leftOfferCount: int, rightOfferCount: int}>
     */
    public function findDuplicateCandidates(): array
    {
        /** @var list<ProductReference> $references */
        $references = $this->productReferenceRepository->createQueryBuilder('pr')
            ->join('pr.brand', 'b')
            ->join('pr.category', 'c')
            ->andWhere('pr.status != :archived')
            ->setParameter('archived', ProductReferenceStatus::Archived)
            ->orderBy('pr.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        $barcodeGroups = [];
        $identityGroups = [];
        foreach ($references as $reference) {
            $barcode = $this->clean($reference->getBarcode());
            if (null !== $barcode) {
                $barcodeGroups[$barcode][] = $reference;
                continue;
            }

            $identityGroups[$this->identityKey($reference)][] = $reference;
        }

        $candidates = [];
        foreach ($barcodeGroups as $group) {
            $this->appendPairs($candidates, $group, 'barcode', $this->clean($group[0]->getBarcode()));
        }
        foreach ($identityGroups as $group) {
            $this->appendPairs($candidates, $group, 'identity', null);
        }

        return $candidates;
    }

    public function matchReason(ProductReference $left, ProductReference $right): string
    {
        $leftBarcode = $this->clean($left->getBarcode());
        $rightBarcode = $this->clean($right->getBarcode());
        if (null !== $leftBarcode && $leftBarcode === $rightBarcode) {
            return 'barcode';
        }

        if ($this->identityKey($left) === $this->identityKey($right)) {
            return 'identity';
        }

        return 'manual';
    }

    public function countMerchantOffers(ProductReference $productReference): int
    {
        return (int) $this->entityManager->getRepository(MerchantProduct::class)->count([
            'productReference' => $productReference,
        ]);
    }

    /**
     * @return array{history: ProductReferenceMergeHistory, movedOfferCount: int, removedConflictingOfferCount: int}
     */
    public function merge(ProductReference $absorbed, ProductReference $kept, User $admin): array
    {
        if ($absorbed->getId()->equals($kept->getId())) {
            throw new ConflictHttpException('ADMIN_PRODUCT_REFERENCE_MERGE_SAME_REFERENCE');
        }

        if (
            ProductReferenceStatus::Archived === $absorbed->getStatus()
            || ProductReferenceStatus::Archived === $kept->getStatus()
        ) {
            throw new UnprocessableEntityHttpException('ADMIN_PRODUCT_REFERENCE_IS_ARCHIVED');
        }

        $reason = $this->matchReason($absorbed, $kept);
        $movedOfferCount = 0;
        $removedConflictingOfferCount = 0;

        /** @var list<MerchantProduct> $absorbedOffers */
        $absorbedOffers = $this->entityManager->getRepository(MerchantProduct::class)->findBy([
            'productReference' => $absorbed,
        ]);

        foreach ($absorbedOffers as $absorbedOffer) {
            $keptOffer = $this->entityManager->getRepository(MerchantProduct::class)->findOneBy([
                'shop' => $absorbedOffer->getShop(),
                'productReference' => $kept,
            ]);

            if (!$keptOffer instanceof MerchantProduct) {
                $absorbedOffer->setProductReference($kept);
                ++$movedOfferCount;
                continue;
            }

            $this->moveLinesToKeptOffer($absorbedOffer, $keptOffer);
            $this->entityManager->remove($absorbedOffer);
            ++$removedConflictingOfferCount;
        }

        $absorbed->setStatus(ProductReferenceStatus::Archived);

        $history = new ProductReferenceMergeHistory(
            keptProductReference: $kept,
            absorbedProductReference: $absorbed,
            mergedByAdmin: $admin,
            reason: $reason,
            movedOfferCount: $movedOfferCount,
            removedConflictingOfferCount: $removedConflictingOfferCount,
            snapshot: [
                'kept' => $this->snapshot($kept),
                'absorbed' => $this->snapshot($absorbed),
            ],
        );
        $this->entityManager->persist($history);

        return [
            'history' => $history,
            'movedOfferCount' => $movedOfferCount,
            'removedConflictingOfferCount' => $removedConflictingOfferCount,
        ];
    }

    /**
     * @param list<array{left: ProductReference, right: ProductReference, reason: string, barcode: ?string, leftOfferCount: int, rightOfferCount: int}> $candidates
     * @param list<ProductReference>                                                                                                                    $group
     */
    private function appendPairs(array &$candidates, array $group, string $reason, ?string $barcode): void
    {
        $count = \count($group);
        if ($count < 2) {
            return;
        }

        for ($i = 0; $i < $count; ++$i) {
            for ($j = $i + 1; $j < $count; ++$j) {
                $left = $group[$i];
                $right = $group[$j];
                $candidates[] = [
                    'left' => $left,
                    'right' => $right,
                    'reason' => $reason,
                    'barcode' => $barcode,
                    'leftOfferCount' => $this->countMerchantOffers($left),
                    'rightOfferCount' => $this->countMerchantOffers($right),
                ];
            }
        }
    }

    private function moveLinesToKeptOffer(MerchantProduct $absorbedOffer, MerchantProduct $keptOffer): void
    {
        /** @var list<KadhiaLine> $kadhiaLines */
        $kadhiaLines = $this->entityManager->getRepository(KadhiaLine::class)->findBy([
            'merchantProduct' => $absorbedOffer,
        ]);
        foreach ($kadhiaLines as $line) {
            $duplicate = $this->entityManager->getRepository(KadhiaLine::class)->findOneBy([
                'kadhia' => $line->getKadhia(),
                'merchantProduct' => $keptOffer,
            ]);
            if ($duplicate instanceof KadhiaLine) {
                $mergedQuantity = $duplicate->getQuantity() + $line->getQuantity();
                $mergedSubtotal = bcadd(
                    bcmul($duplicate->getUnitPriceTnd(), (string) $duplicate->getQuantity(), 3),
                    bcmul($line->getUnitPriceTnd(), (string) $line->getQuantity(), 3),
                    3,
                );
                $duplicate
                    ->setQuantity($mergedQuantity)
                    ->setUnitPriceTnd(bcdiv($mergedSubtotal, (string) $mergedQuantity, 3));
                $line->getKadhia()->removeLine($line);
                $this->entityManager->remove($line);
                continue;
            }
            $line->setMerchantProduct($keptOffer);
        }

        /** @var list<OrderLine> $orderLines */
        $orderLines = $this->entityManager->getRepository(OrderLine::class)->findBy([
            'merchantProduct' => $absorbedOffer,
        ]);
        foreach ($orderLines as $line) {
            $duplicate = $this->entityManager->getRepository(OrderLine::class)->findOneBy([
                'order' => $line->getOrder(),
                'merchantProduct' => $keptOffer,
            ]);
            if ($duplicate instanceof OrderLine) {
                $order = $duplicate->getOrder();
                $mergedQuantity = $duplicate->getQuantity() + $line->getQuantity();
                $mergedLineTotal = bcadd($duplicate->getLineTotalTnd(), $line->getLineTotalTnd(), 3);
                $duplicate
                    ->setQuantity($mergedQuantity)
                    ->setUnitPriceTnd(bcdiv($mergedLineTotal, (string) $mergedQuantity, 3))
                    ->setLineTotalTnd($mergedLineTotal)
                    ->markPrepared($duplicate->isPrepared() && $line->isPrepared());
                $order->removeLine($line);
                $this->entityManager->remove($line);
                $order->recomputeTotal();
                continue;
            }
            $line->setMerchantProduct($keptOffer);
        }
    }

    private function identityKey(ProductReference $reference): string
    {
        return implode('|', [
            $reference->getBrand()->getId()->toRfc4122(),
            $reference->getCategory()->getId()->toRfc4122(),
            $this->normalize($reference->getNameFr()),
            $this->normalize($reference->getVariantFr() ?? ''),
            null === $reference->getVolume() ? '' : bcadd($reference->getVolume(), '0', 3),
            $reference->getUnit()->value,
            strtoupper($reference->getCountry()),
            (string) $reference->getPackQuantity(),
        ]);
    }

    private function clean(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $cleaned = trim($value);

        return '' === $cleaned ? null : $cleaned;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $value));
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(ProductReference $reference): array
    {
        return [
            'id' => $reference->getId()->toRfc4122(),
            'name_fr' => $reference->getNameFr(),
            'brand_id' => $reference->getBrand()->getId()->toRfc4122(),
            'category_id' => $reference->getCategory()->getId()->toRfc4122(),
            'volume' => $reference->getVolume(),
            'unit' => $reference->getUnit()->value,
            'barcode' => $reference->getBarcode(),
            'status' => $reference->getStatus()->value,
        ];
    }
}
