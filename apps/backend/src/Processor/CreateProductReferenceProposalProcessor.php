<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\ProductReferenceProposalCreateInput;
use App\Entity\MerchantLocalProduct;
use App\Entity\ProductReferenceProposal;
use App\Entity\User;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
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
        #[Autowire(service: 'monolog.logger.catalog')]
        private LoggerInterface $logger,
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

        $category = null;
        if (null !== $data->categoryId) {
            $category = $this->categoryRepository->find($data->categoryId);
            if (null === $category) {
                throw new NotFoundHttpException('CATEGORY_NOT_FOUND');
            }
        }

        $brand = null;
        if (null !== $data->brandId) {
            $brand = $this->brandRepository->find($data->brandId);
            if (null === $brand) {
                throw new NotFoundHttpException('BRAND_NOT_FOUND');
            }
        }

        $localProduct = null;
        if (null !== $data->localProductId) {
            if (!Uuid::isValid($data->localProductId)) {
                throw new NotFoundHttpException('LOCAL_PRODUCT_NOT_FOUND');
            }
            $localProduct = $this->entityManager->find(MerchantLocalProduct::class, Uuid::fromString($data->localProductId));
            if (null === $localProduct) {
                throw new NotFoundHttpException('LOCAL_PRODUCT_NOT_FOUND');
            }
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('MERCHANT_FORBIDDEN');
        }

        $proposal = (new ProductReferenceProposal())
            ->setProposedBy($user)
            ->setShop($shop)
            ->setNameFr($data->nameFr)
            ->setNameAr($data->nameAr)
            ->setBrand($brand)
            ->setBrandName($data->brandName)
            ->setCategory($category)
            ->setCategoryNameProposed($data->categoryNameProposed)
            ->setLocalProduct($localProduct)
            ->setVariantFr($data->variantFr)
            ->setVolume($data->volume)
            ->setUnit($data->unit)
            ->setBarcode($data->barcode);

        $this->entityManager->persist($proposal);
        $this->entityManager->flush();

        $this->logger->info('catalog.product_proposal.created', [
            'proposal_id' => $proposal->getId()->toRfc4122(),
            'store_id' => $storeId,
        ]);
    }
}
