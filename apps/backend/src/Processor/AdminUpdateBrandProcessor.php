<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminBrandOutput;
use App\Dto\AdminUpdateBrandInput;
use App\Provider\AdminBrandItemProvider;
use App\Repository\AdminBrandRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<AdminUpdateBrandInput, AdminBrandOutput>
 */
final readonly class AdminUpdateBrandProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminBrandRepository $adminBrandRepository,
        private RequestStack $requestStack,
        private SluggerInterface $slugger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminBrandOutput
    {
        if (!$data instanceof AdminUpdateBrandInput) {
            throw new \InvalidArgumentException('AdminUpdateBrandInput expected.');
        }

        $brandId = (string) ($uriVariables['brandId'] ?? '');
        if (!Uuid::isValid($brandId)) {
            throw new NotFoundHttpException('ADMIN_BRAND_NOT_FOUND');
        }

        $brand = $this->adminBrandRepository->findOne($brandId);
        if (null === $brand) {
            throw new NotFoundHttpException('ADMIN_BRAND_NOT_FOUND');
        }

        $payload = $this->currentPayload();

        if (\array_key_exists('canonicalName', $payload)) {
            if (null === $data->canonicalName) {
                throw new UnprocessableEntityHttpException('ADMIN_BRAND_CANONICAL_NAME_REQUIRED');
            }
            $canonicalName = trim($data->canonicalName);
            if ('' === $canonicalName) {
                throw new UnprocessableEntityHttpException('ADMIN_BRAND_CANONICAL_NAME_BLANK');
            }
            $brand->setCanonicalName($canonicalName);
        }
        if (\array_key_exists('slug', $payload)) {
            if (null === $data->slug || '' === trim($data->slug)) {
                throw new UnprocessableEntityHttpException('ADMIN_BRAND_SLUG_BLANK');
            }
            $slug = $this->slugger->slug(trim($data->slug))->lower()->toString();
            $existing = $this->adminBrandRepository->findOneBySlug($slug);
            if (null !== $existing && !$existing->getId()->equals($brand->getId())) {
                throw new UnprocessableEntityHttpException('ADMIN_BRAND_SLUG_DUPLICATE');
            }
            $brand->setSlug($slug);
        }
        if (\array_key_exists('aliases', $payload)) {
            $brand->setAliases($data->aliases ?? []);
        }
        if (\array_key_exists('country', $payload)) {
            $country = null !== $data->country ? trim($data->country) : null;
            $brand->setCountry('' === $country ? null : $country);
        }
        if (\array_key_exists('isActive', $payload) && null !== $data->isActive) {
            $brand->setActive((bool) $data->isActive);
        }

        $this->adminBrandRepository->save($brand);

        return AdminBrandItemProvider::toOutput($brand);
    }

    /**
     * @return array<string, mixed>
     */
    private function currentPayload(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request || '' === $request->getContent()) {
            return [];
        }

        $payload = json_decode($request->getContent(), true);

        return \is_array($payload) ? $payload : [];
    }
}
