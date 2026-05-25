<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantCategoryOutput;
use App\Dto\MerchantCategoryUpdateInput;
use App\Entity\MerchantCategory;
use App\Mapper\MerchantCategoryMapper;
use App\Repository\MerchantCategoryRepository;
use App\Security\MerchantShopAccessChecker;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<MerchantCategoryUpdateInput, MerchantCategoryOutput>
 */
final readonly class UpdateMerchantCategoryProcessor implements ProcessorInterface
{
    public function __construct(
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
        if (!$data instanceof MerchantCategoryUpdateInput) {
            throw new \InvalidArgumentException('MerchantCategoryUpdateInput expected.');
        }

        $merchantCategory = $this->findMerchantCategory((string) ($uriVariables['merchantCategoryId'] ?? ''));
        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($merchantCategory->getShop());

        if (null !== $data->nameFr) {
            $merchantCategory->setNameFr($this->normalizeRequiredText($data->nameFr));
        }
        if ($data->hasNameAr()) {
            $merchantCategory->setNameAr($this->normalizeOptionalText($data->getNameAr()));
        }
        if ($data->hasParentId()) {
            $merchantCategory->setParent($this->resolveParent($data->getParentId(), $merchantCategory));
        }
        if (null !== $data->sortOrder) {
            $merchantCategory->setSortOrder($data->sortOrder);
        }
        if (null !== $data->active) {
            $merchantCategory->setActive($data->active);
        }

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('MERCHANT_CATEGORY_ALREADY_EXISTS');
        }

        return $this->merchantCategoryMapper->toOutput($merchantCategory);
    }

    private function findMerchantCategory(string $merchantCategoryId): MerchantCategory
    {
        if (!Uuid::isValid($merchantCategoryId)) {
            throw new NotFoundHttpException('MERCHANT_CATEGORY_NOT_FOUND');
        }

        $merchantCategory = $this->merchantCategoryRepository->find($merchantCategoryId);
        if (null === $merchantCategory) {
            throw new NotFoundHttpException('MERCHANT_CATEGORY_NOT_FOUND');
        }

        return $merchantCategory;
    }

    private function resolveParent(?string $parentId, MerchantCategory $merchantCategory): ?MerchantCategory
    {
        if (null === $parentId) {
            return null;
        }

        if (!Uuid::isValid($parentId)) {
            throw new NotFoundHttpException('MERCHANT_CATEGORY_PARENT_NOT_FOUND');
        }

        if ($merchantCategory->getId()->equals(Uuid::fromString($parentId))) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'MERCHANT_CATEGORY_PARENT_SELF_INVALID');
        }

        $parent = $this->merchantCategoryRepository->find($parentId);
        if (null === $parent) {
            throw new NotFoundHttpException('MERCHANT_CATEGORY_PARENT_NOT_FOUND');
        }

        if (!$parent->getShop()->getId()->equals($merchantCategory->getShop()->getId())) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'MERCHANT_CATEGORY_PARENT_SHOP_INVALID');
        }
        if (!$parent->isActive()) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'MERCHANT_CATEGORY_PARENT_INACTIVE');
        }
        $ancestor = $parent->getParent();
        while (null !== $ancestor) {
            if ($ancestor->getId()->equals($merchantCategory->getId())) {
                throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'MERCHANT_CATEGORY_PARENT_CYCLE_INVALID');
            }

            $ancestor = $ancestor->getParent();
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
}
