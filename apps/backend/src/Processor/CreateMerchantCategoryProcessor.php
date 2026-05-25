<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantCategoryOutput;
use App\Dto\MerchantCategoryCreateInput;
use App\Entity\MerchantCategory;
use App\Mapper\MerchantCategoryMapper;
use App\Repository\MerchantCategoryRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<MerchantCategoryCreateInput, MerchantCategoryOutput>
 */
final readonly class CreateMerchantCategoryProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private MerchantCategoryRepository $merchantCategoryRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private MerchantCategoryMapper $merchantCategoryMapper,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantCategoryOutput
    {
        if (!$data instanceof MerchantCategoryCreateInput) {
            throw new \InvalidArgumentException('MerchantCategoryCreateInput expected.');
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

        $parent = $this->resolveParent($data->parentId);
        if (null !== $parent && !$parent->getShop()->getId()->equals($shop->getId())) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'MERCHANT_CATEGORY_PARENT_SHOP_INVALID');
        }
        if (null !== $parent && !$parent->isActive()) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'MERCHANT_CATEGORY_PARENT_INACTIVE');
        }

        $merchantCategory = (new MerchantCategory())
            ->setShop($shop)
            ->setNameFr($this->normalizeRequiredText($data->nameFr))
            ->setSlug($this->generateUniqueSlug($shop, $data->nameFr))
            ->setNameAr($this->normalizeOptionalText($data->nameAr))
            ->setParent($parent)
            ->setSortOrder($data->sortOrder)
            ->setActive($data->active);

        $this->entityManager->persist($merchantCategory);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('MERCHANT_CATEGORY_ALREADY_EXISTS');
        }

        return $this->merchantCategoryMapper->toOutput($merchantCategory);
    }

    private function resolveParent(?string $parentId): ?MerchantCategory
    {
        if (null === $parentId) {
            return null;
        }

        $parent = $this->merchantCategoryRepository->find($parentId);
        if (null === $parent) {
            throw new NotFoundHttpException('MERCHANT_CATEGORY_PARENT_NOT_FOUND');
        }

        return $parent;
    }

    private function normalizeRequiredText(string $value): string
    {
        return trim($value);
    }

    private function normalizeOptionalText(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }

    private function generateUniqueSlug(\App\Entity\Shop $shop, string $nameFr): string
    {
        $baseSlug = $this->slugify($nameFr);
        $slug = $baseSlug;
        $suffix = 2;

        while (null !== $this->merchantCategoryRepository->findOneForShopAndSlug($shop, $slug)) {
            $slug = $baseSlug.'-'.$suffix;
            ++$suffix;
        }

        return $slug;
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = strtr($value, [
            'à' => 'a',
            'â' => 'a',
            'ä' => 'a',
            'ç' => 'c',
            'é' => 'e',
            'è' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'î' => 'i',
            'ï' => 'i',
            'ô' => 'o',
            'ö' => 'o',
            'ù' => 'u',
            'û' => 'u',
            'ü' => 'u',
        ]);
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $normalized = false === $transliterated ? $value : $transliterated;
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($normalized)) ?? '';
        $slug = trim($slug, '-');

        return '' === $slug ? 'categorie' : $slug;
    }
}
