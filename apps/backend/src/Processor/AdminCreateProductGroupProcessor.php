<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminProductGroupOutput;
use App\Dto\AdminCreateProductGroupInput;
use App\Entity\ProductGroup;
use App\Enum\ProductGroupVisibility;
use App\Provider\AdminProductGroupItemProvider;
use App\Repository\AdminProductGroupRepository;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * @implements ProcessorInterface<AdminCreateProductGroupInput, AdminProductGroupOutput>
 */
final readonly class AdminCreateProductGroupProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminProductGroupRepository $adminProductGroupRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminProductGroupOutput
    {
        if (!$data instanceof AdminCreateProductGroupInput) {
            throw new \InvalidArgumentException('AdminCreateProductGroupInput expected.');
        }

        $nameFr = trim((string) $data->nameFr);
        if ('' === $nameFr) {
            throw new UnprocessableEntityHttpException('ADMIN_PRODUCT_GROUP_NAME_FR_BLANK');
        }

        $slug = null !== $data->slug && '' !== trim($data->slug)
            ? trim($data->slug)
            : $this->generateUniqueSlug($nameFr);

        if (null !== $this->adminProductGroupRepository->findOneBySlug($slug)) {
            throw new UnprocessableEntityHttpException('ADMIN_PRODUCT_GROUP_SLUG_DUPLICATE');
        }

        $group = (new ProductGroup())
            ->setNameFr($nameFr)
            ->setNameAr($this->nullableTrim($data->nameAr))
            ->setSlug($slug)
            ->setDescriptionFr($this->nullableTrim($data->descriptionFr))
            ->setDescriptionAr($this->nullableTrim($data->descriptionAr))
            ->setMarketCountry($this->countryOrDefault($data->marketCountry))
            ->setVisibility(null !== $data->visibility ? ProductGroupVisibility::from($data->visibility) : ProductGroupVisibility::Merchant)
            ->setIcon($this->nullableTrim($data->icon))
            ->setSortOrder(null !== $data->sortOrder ? (int) $data->sortOrder : 0);

        $this->adminProductGroupRepository->save($group);

        return AdminProductGroupItemProvider::toOutput($group, includeItems: true);
    }

    private function generateUniqueSlug(string $nameFr): string
    {
        $base = $this->slugify($nameFr);
        $slug = $base;
        $suffix = 2;

        while (null !== $this->adminProductGroupRepository->findOneBySlug($slug)) {
            $suffixText = '-'.$suffix;
            $slug = mb_substr($base, 0, 180 - mb_strlen($suffixText)).$suffixText;
            ++$suffix;
        }

        return $slug;
    }

    private function slugify(string $name): string
    {
        $slug = (new AsciiSlugger('fr'))->slug($name)->lower()->toString();

        return '' === $slug ? 'product-group' : mb_substr($slug, 0, 180);
    }

    private function nullableTrim(?string $value): ?string
    {
        $trimmed = null !== $value ? trim($value) : null;

        return null === $trimmed || '' === $trimmed ? null : $trimmed;
    }

    private function countryOrDefault(?string $country): string
    {
        $trimmed = null !== $country ? trim($country) : null;

        return null === $trimmed || '' === $trimmed ? 'TN' : strtoupper($trimmed);
    }
}
