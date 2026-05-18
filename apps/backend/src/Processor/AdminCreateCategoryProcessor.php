<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminCategoryOutput;
use App\Dto\AdminCreateCategoryInput;
use App\Entity\Category;
use App\Provider\AdminCategoryItemProvider;
use App\Repository\AdminCategoryRepository;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * @implements ProcessorInterface<AdminCreateCategoryInput, AdminCategoryOutput>
 */
final readonly class AdminCreateCategoryProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminCategoryRepository $adminCategoryRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminCategoryOutput
    {
        if (!$data instanceof AdminCreateCategoryInput) {
            throw new \InvalidArgumentException('AdminCreateCategoryInput expected.');
        }

        $nameFr = trim((string) $data->nameFr);
        if ('' === $nameFr) {
            throw new UnprocessableEntityHttpException('ADMIN_CATEGORY_NAME_FR_BLANK');
        }

        $slug = null !== $data->slug && '' !== trim($data->slug)
            ? trim($data->slug)
            : $this->generateUniqueSlug($nameFr);

        if (null !== $this->adminCategoryRepository->findOneBySlug($slug)) {
            throw new UnprocessableEntityHttpException('ADMIN_CATEGORY_SLUG_DUPLICATE');
        }

        $category = (new Category())
            ->setNameFr($nameFr)
            ->setNameAr($data->nameAr)
            ->setSlug($slug)
            ->setActive(true);

        $this->adminCategoryRepository->save($category);

        return AdminCategoryItemProvider::toOutput($category);
    }

    private function generateUniqueSlug(string $nameFr): string
    {
        $base = $this->slugify($nameFr);
        $slug = $base;
        $suffix = 2;

        while (null !== $this->adminCategoryRepository->findOneBySlug($slug)) {
            $suffixText = '-'.$suffix;
            $slug = mb_substr($base, 0, 180 - mb_strlen($suffixText)).$suffixText;
            ++$suffix;
        }

        return $slug;
    }

    private function slugify(string $name): string
    {
        $slug = (new AsciiSlugger('fr'))->slug($name)->lower()->toString();

        return '' === $slug ? 'category' : mb_substr($slug, 0, 180);
    }
}
