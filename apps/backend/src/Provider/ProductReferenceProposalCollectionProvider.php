<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\ProductReferenceProposalOutput;
use App\Entity\ProductReferenceProposal;
use App\Repository\ProductReferenceProposalRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<ProductReferenceProposalOutput>
 */
final readonly class ProductReferenceProposalCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private ProductReferenceProposalRepository $productReferenceProposalRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return list<ProductReferenceProposalOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        $proposals = $this->productReferenceProposalRepository->findForShop($shop);

        return array_map(
            static fn (ProductReferenceProposal $p) => new ProductReferenceProposalOutput(
                $p->getId()->toRfc4122(),
                $p->getNameFr(),
                $p->getNameAr(),
                $p->getStatus()->value,
                $p->getCategory()->getNameFr(),
                $p->getCategory()->getSlug(),
                $p->getBrand()?->getCanonicalName(),
                $p->getBrandName(),
                $p->getBarcode(),
                $p->getRejectionReason(),
                $p->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ),
            $proposals,
        );
    }
}
