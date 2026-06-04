<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Shop;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\MerchantStoreQrPdfGenerator;
use App\Service\MerchantStoreQrTargetUrlFactory;
use App\Service\QrCodePngGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final readonly class MerchantStoreQrAssetController
{
    public function __construct(
        private ShopRepository $shopRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private MerchantStoreQrTargetUrlFactory $targetUrlFactory,
        private QrCodePngGenerator $pngGenerator,
        private MerchantStoreQrPdfGenerator $pdfGenerator,
    ) {
    }

    #[Route('/api/merchant/stores/{storeId}/qr-code.png', name: 'api_merchant_store_qr_png', methods: ['GET'])]
    public function png(string $storeId): Response
    {
        $shop = $this->getOwnedShop($storeId);
        $png = $this->pngGenerator->generate($this->targetUrlFactory->create($shop));

        return new Response($png, Response::HTTP_OK, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => \sprintf('attachment; filename="%s.png"', $this->filename($shop)),
        ]);
    }

    #[Route('/api/merchant/stores/{storeId}/qr-code.pdf', name: 'api_merchant_store_qr_pdf', methods: ['GET'])]
    public function pdf(string $storeId): Response
    {
        $shop = $this->getOwnedShop($storeId);
        $targetUrl = $this->targetUrlFactory->create($shop);
        $png = $this->pngGenerator->generate($targetUrl);
        $pdf = $this->pdfGenerator->generate($shop->getName(), $targetUrl, $png);

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => \sprintf('attachment; filename="%s.pdf"', $this->filename($shop)),
        ]);
    }

    private function getOwnedShop(string $storeId): Shop
    {
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('MERCHANT_STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (!$shop instanceof Shop) {
            throw new NotFoundHttpException('MERCHANT_STORE_NOT_FOUND');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        return $shop;
    }

    private function filename(Shop $shop): string
    {
        $slug = preg_replace('/[^a-z0-9-]+/', '-', strtolower($shop->getSlug()));
        $slug = \is_string($slug) ? trim($slug, '-') : '';
        if ('' === $slug) {
            $slug = 'superette';
        }

        return 'qr-'.$slug;
    }
}
