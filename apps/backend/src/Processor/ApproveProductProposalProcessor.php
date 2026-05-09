<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Brand;
use App\Entity\ProductReference;
use App\Entity\ProductReferenceProposal;
use App\Enum\ProductReferenceProposalStatus;
use App\Enum\ProductReferenceStatus;
use App\Repository\BrandRepository;
use App\Repository\ProductReferenceProposalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<object, void>
 */
final readonly class ApproveProductProposalProcessor implements ProcessorInterface
{
    public function __construct(
        private ProductReferenceProposalRepository $proposalRepository,
        private BrandRepository $brandRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $proposalId = (string) ($uriVariables['proposalId'] ?? '');
        if (!Uuid::isValid($proposalId)) {
            throw new NotFoundHttpException('PROPOSAL_NOT_FOUND');
        }

        $proposal = $this->proposalRepository->find($proposalId);
        if (!$proposal instanceof ProductReferenceProposal) {
            throw new NotFoundHttpException('PROPOSAL_NOT_FOUND');
        }

        $brand = $this->resolveBrand($proposal);

        $productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($proposal->getCategory())
            ->setNameFr($proposal->getNameFr())
            ->setNameAr($proposal->getNameAr())
            ->setVariantFr($proposal->getVariantFr())
            ->setVolume($proposal->getVolume())
            ->setUnit($proposal->getUnit())
            ->setBarcode($proposal->getBarcode())
            ->setStatus(ProductReferenceStatus::Approved);

        $this->entityManager->persist($productReference);

        $proposal->setStatus(ProductReferenceProposalStatus::Approved);
        $proposal->setCreatedProductReference($productReference);

        $this->entityManager->flush();
    }

    private function resolveBrand(ProductReferenceProposal $proposal): Brand
    {
        if (null !== $proposal->getBrand()) {
            return $proposal->getBrand();
        }

        $brandName = $proposal->getBrandName();
        if (null === $brandName || '' === $brandName) {
            throw new UnprocessableEntityHttpException('BRAND_REQUIRED_FOR_APPROVAL');
        }

        $slug = $this->slugify($brandName);
        $existing = $this->brandRepository->findOneBy(['slug' => $slug]);
        if (null !== $existing) {
            return $existing;
        }

        $brand = (new Brand())
            ->setCanonicalName($brandName)
            ->setSlug($this->generateUniqueSlug($slug));

        $this->entityManager->persist($brand);

        return $brand;
    }

    private function slugify(string $name): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        $slug = strtolower($ascii);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }

    private function generateUniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 2;

        while (null !== $this->brandRepository->findOneBy(['slug' => $slug])) {
            $slug = $base.'-'.$i;
            ++$i;
        }

        return $slug;
    }
}
