<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\ProductReferenceProposalCreateInput;
use App\Entity\ProductReferenceProposal;
use App\Entity\User;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<ProductReferenceProposalCreateInput, void>
 */
final readonly class CreateProductReferenceProposalProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private BrandRepository $brandRepository,
        private CategoryRepository $categoryRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
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
        if (!$data instanceof ProductReferenceProposalCreateInput) {
            throw new \InvalidArgumentException('ProductReferenceProposalCreateInput expected.');
        }

        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        $category = $this->categoryRepository->find($data->categoryId);
        if (null === $category) {
            throw new NotFoundHttpException('CATEGORY_NOT_FOUND');
        }

        $brand = null;
        if (null !== $data->brandId) {
            $brand = $this->brandRepository->find($data->brandId);
            if (null === $brand) {
                throw new NotFoundHttpException('BRAND_NOT_FOUND');
            }
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \RuntimeException('Authenticated user must be an instance of User.');
        }

        $proposal = (new ProductReferenceProposal())
            ->setProposedBy($user)
            ->setShop($shop)
            ->setNameFr($data->nameFr)
            ->setNameAr($data->nameAr)
            ->setBrand($brand)
            ->setBrandName($data->brandName)
            ->setCategory($category)
            ->setVariantFr($data->variantFr)
            ->setVolume($data->volume)
            ->setUnit($data->unit)
            ->setBarcode($data->barcode);

        $this->entityManager->persist($proposal);
        $this->entityManager->flush();
    }
}
