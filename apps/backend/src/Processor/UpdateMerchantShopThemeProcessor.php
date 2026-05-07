<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantShopThemeOutput;
use App\Dto\ThemeWriteInput;
use App\Entity\ShopTheme;
use App\Mapper\ShopThemeMapper;
use App\Repository\ShopRepository;
use App\Security\Voter\ShopOwnerVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<ThemeWriteInput, MerchantShopThemeOutput>
 */
final readonly class UpdateMerchantShopThemeProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private ShopThemeMapper $shopThemeMapper,
        private EntityManagerInterface $entityManager,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantShopThemeOutput
    {
        if (!$data instanceof ThemeWriteInput) {
            throw new \InvalidArgumentException('ThemeWriteInput expected.');
        }

        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        if (!$this->authorizationChecker->isGranted(ShopOwnerVoter::SHOP_OWNER, $shop)) {
            throw new AccessDeniedHttpException('SHOP_THEME_FORBIDDEN');
        }

        $shopTheme = $shop->getTheme();
        if (null === $shopTheme) {
            $shopTheme = (new ShopTheme())->setShop($shop);
            $shop->setTheme($shopTheme);
            $this->entityManager->persist($shopTheme);
        }

        $this->shopThemeMapper->applyWriteInput($shopTheme, $data);
        $this->entityManager->flush();

        return $this->shopThemeMapper->toMerchantOutput($shopTheme, $storeId);
    }
}
