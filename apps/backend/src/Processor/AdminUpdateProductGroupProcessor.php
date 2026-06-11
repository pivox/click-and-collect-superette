<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminProductGroupOutput;
use App\Dto\AdminUpdateProductGroupInput;
use App\Enum\ProductGroupVisibility;
use App\Provider\AdminProductGroupItemProvider;
use App\Repository\AdminProductGroupRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<AdminUpdateProductGroupInput, AdminProductGroupOutput>
 */
final readonly class AdminUpdateProductGroupProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminProductGroupItemProvider $itemProvider,
        private AdminProductGroupRepository $adminProductGroupRepository,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminProductGroupOutput
    {
        if (!$data instanceof AdminUpdateProductGroupInput) {
            throw new \InvalidArgumentException('AdminUpdateProductGroupInput expected.');
        }

        $group = $this->itemProvider->findGroup((string) ($uriVariables['groupId'] ?? ''));
        $payload = $this->currentPayload();

        if (\array_key_exists('nameFr', $payload)) {
            $nameFr = null !== $data->nameFr ? trim($data->nameFr) : '';
            if ('' === $nameFr) {
                throw new UnprocessableEntityHttpException('ADMIN_PRODUCT_GROUP_NAME_FR_BLANK');
            }
            $group->setNameFr($nameFr);
        }

        if (\array_key_exists('nameAr', $payload)) {
            $group->setNameAr($this->nullableTrim($data->nameAr));
        }

        if (\array_key_exists('slug', $payload)) {
            $slug = null !== $data->slug ? trim($data->slug) : '';
            if ('' === $slug) {
                throw new UnprocessableEntityHttpException('ADMIN_PRODUCT_GROUP_SLUG_BLANK');
            }
            $existing = $this->adminProductGroupRepository->findOneBySlug($slug);
            if (null !== $existing && !$existing->getId()->equals($group->getId())) {
                throw new UnprocessableEntityHttpException('ADMIN_PRODUCT_GROUP_SLUG_DUPLICATE');
            }
            $group->setSlug($slug);
        }

        if (\array_key_exists('descriptionFr', $payload)) {
            $group->setDescriptionFr($this->nullableTrim($data->descriptionFr));
        }
        if (\array_key_exists('descriptionAr', $payload)) {
            $group->setDescriptionAr($this->nullableTrim($data->descriptionAr));
        }
        if (\array_key_exists('marketCountry', $payload)) {
            $group->setMarketCountry($this->countryOrDefault($data->marketCountry));
        }
        if (\array_key_exists('visibility', $payload) && null !== $data->visibility) {
            $group->setVisibility(ProductGroupVisibility::from($data->visibility));
        }
        if (\array_key_exists('icon', $payload)) {
            $group->setIcon($this->nullableTrim($data->icon));
        }
        if (\array_key_exists('sortOrder', $payload) && null !== $data->sortOrder) {
            $group->setSortOrder((int) $data->sortOrder);
        }

        $this->adminProductGroupRepository->save($group);

        return AdminProductGroupItemProvider::toOutput($group, includeItems: true);
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
