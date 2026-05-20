<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminBrandOutput;
use App\Dto\AdminCreateBrandInput;
use App\Entity\Brand;
use App\Provider\AdminBrandItemProvider;
use App\Repository\AdminBrandRepository;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * @implements ProcessorInterface<AdminCreateBrandInput, AdminBrandOutput>
 */
final readonly class AdminCreateBrandProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminBrandRepository $adminBrandRepository,
        private SluggerInterface $slugger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminBrandOutput
    {
        if (!$data instanceof AdminCreateBrandInput) {
            throw new \InvalidArgumentException('AdminCreateBrandInput expected.');
        }

        $canonicalName = trim((string) $data->canonicalName);

        $slug = null !== $data->slug && '' !== trim($data->slug)
            ? trim($data->slug)
            : $this->generateUniqueSlug($canonicalName);

        if (null !== $this->adminBrandRepository->findOneBySlug($slug)) {
            throw new UnprocessableEntityHttpException('ADMIN_BRAND_SLUG_DUPLICATE');
        }

        $brand = (new Brand())
            ->setCanonicalName($canonicalName)
            ->setSlug($slug)
            ->setAliases($data->aliases ?? [])
            ->setCountry($data->country)
            ->setActive(true);

        $this->adminBrandRepository->save($brand);

        return AdminBrandItemProvider::toOutput($brand);
    }

    private function generateUniqueSlug(string $canonicalName): string
    {
        $base = $this->slugify($canonicalName);
        $slug = $base;
        $suffix = 2;

        while (null !== $this->adminBrandRepository->findOneBySlug($slug)) {
            $suffixText = '-'.$suffix;
            $slug = mb_substr($base, 0, 180 - mb_strlen($suffixText)).$suffixText;
            ++$suffix;
        }

        return $slug;
    }

    private function slugify(string $name): string
    {
        $slug = $this->slugger->slug($name)->lower()->toString();

        return '' === $slug ? 'brand' : mb_substr($slug, 0, 180);
    }
}
